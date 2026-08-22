<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Exceptions\AiUnavailableException;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Collection;
use App\Models\LiteratureReview;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Retrieval-augmented generation: retrieve → assemble → generate → cite.
 *
 * The model is instructed to answer only from the supplied context and to say
 * when the answer is not in the papers, which is what makes the citations
 * meaningful rather than decorative.
 */
class RagService
{
    /** Chunks retrieved per question. */
    private const TOP_K = 6;

    /**
     * Characters of retrieved context to send.
     *
     * Derived from the provider's per-minute token budget rather than being a
     * fixed constant, so a small free-tier allowance shrinks the context
     * instead of producing a rejected request. A quarter of the budget is
     * reserved for the question, the instructions and the prompt scaffolding.
     */
    private function contextBudget(): int
    {
        return (int) max(2_000, $this->ai->promptCharBudget() * 0.75);
    }

    public function __construct(
        private AiService $ai,
        private VectorSearchService $vectors,
        private ActivityService $activities,
    ) {}

    public function isConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Structured one-click summary of a single paper.
     */
    public function summarizePaper(Paper $paper): string
    {
        $text = $this->paperText($paper);

        if ($text === '') {
            throw new AiUnavailableException(
                'This paper has no extractable text, so it cannot be summarised. Scanned PDFs need OCR, which is out of scope.'
            );
        }

        $prompt = <<<PROMPT
        Summarise the following academic paper.

        Use exactly these four sections, as markdown headings:
        ## TL;DR
        ## Key contributions
        ## Method
        ## Limitations

        Base every statement on the text provided. If the text does not cover a
        section, write "Not stated in the provided text." under that heading.

        PAPER TITLE: {$paper->title}

        PAPER TEXT:
        {$text}
        PROMPT;

        return $this->ai->generate($prompt, $this->systemInstruction());
    }

    /**
     * Ask a question scoped to one paper.
     *
     * @return array{answer: string, citations: list<array<string, mixed>>, session: ChatSession}
     */
    public function askPaper(Paper $paper, User $user, string $question): array
    {
        $hits = $this->vectors->search($question, $user->id, 'paper', $paper->id, self::TOP_K);

        if ($hits->isEmpty()) {
            throw new AiUnavailableException(
                $paper->isIndexed()
                    ? 'Nothing in this paper matched that question.'
                    : 'This paper has not been indexed yet, so it cannot be searched.'
            );
        }

        $session = $this->sessionFor($user, 'paper', $paper->id, null, $question);

        return $this->answer($session, $question, $hits);
    }

    /**
     * Ask a question across everything the user can read.
     *
     * The collection-scoped version below is the same feature narrowed to one
     * reading list. This library-wide entry point exists because requiring a
     * collection first made cross-paper Q&A — the product's differentiator —
     * invisible to anyone who had not built one yet.
     *
     * @return array{answer: string, citations: list<array<string, mixed>>, session: ChatSession}
     */
    public function askLibrary(User $user, string $question): array
    {
        $hits = $this->vectors->search($question, $user->id, 'library', null, self::TOP_K);

        if ($hits->isEmpty()) {
            throw new AiUnavailableException(
                'Nothing in your library matched that question. Papers need an uploaded PDF whose text '
                .'has been indexed before they can be searched.'
            );
        }

        $session = $this->sessionFor($user, 'library', null, null, $question);

        return $this->answer($session, $question, $hits);
    }

    /**
     * Ask a question across a whole collection, citing the source paper for
     * each claim.
     *
     * @return array{answer: string, citations: list<array<string, mixed>>, session: ChatSession}
     */
    public function askCollection(Collection $collection, User $user, string $question): array
    {
        $hits = $this->vectors->search($question, $user->id, 'collection', $collection->id, self::TOP_K);

        if ($hits->isEmpty()) {
            throw new AiUnavailableException(
                'Nothing in this collection matched that question. Papers must have an indexed PDF to be searchable.'
            );
        }

        $session = $this->sessionFor($user, 'collection', null, $collection->id, $question);

        return $this->answer($session, $question, $hits);
    }

    /**
     * Generate and persist a literature-review draft for a collection.
     */
    public function draftReview(Collection $collection, User $user, ?array $paperIds = null): LiteratureReview
    {
        $papers = $collection->papers()
            ->when($paperIds, fn ($q) => $q->whereIn('papers.id', $paperIds))
            ->get();

        if ($papers->isEmpty()) {
            throw new AiUnavailableException('Add papers to this collection before generating a review.');
        }

        $context = $papers
            ->map(function (Paper $paper) {
                $body = $paper->abstract ?: mb_substr((string) $paper->full_text, 0, 2000);

                return sprintf(
                    "[%s] %s (%s, %s)\n%s",
                    $paper->id,
                    $paper->title,
                    $paper->authors ?: 'unknown authors',
                    $paper->year ?: 'n.d.',
                    $body ?: 'No abstract or extracted text available.'
                );
            })
            ->implode("\n\n");

        $context = mb_substr($context, 0, $this->contextBudget());

        $prompt = <<<PROMPT
        Draft a structured literature review over the following papers.

        Structure it as markdown with:
        ## Introduction
        ## Themes across the literature
        ## Methodological approaches
        ## Gaps and open questions
        ## Conclusion

        Refer to papers by their title. Ground every claim in the supplied
        material; if the material is thin on a point, say so rather than
        inventing findings.

        COLLECTION: {$collection->name}

        PAPERS:
        {$context}
        PROMPT;

        $content = $this->ai->generate($prompt, $this->systemInstruction());

        $review = LiteratureReview::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'title' => 'Draft review — '.now()->format('j M Y'),
            'content' => $content,
            'paper_ids' => $papers->pluck('id')->all(),
        ]);

        $this->activities->record($collection, $user, ActivityType::ReviewGenerated);

        return $review;
    }

    /**
     * Assemble the retrieved context, generate an answer, persist the turn.
     *
     * @param  SupportCollection<int, array{chunk: \App\Models\PaperChunk, paper: Paper, score: float}>  $hits
     * @return array{answer: string, citations: list<array<string, mixed>>, session: ChatSession}
     */
    private function answer(ChatSession $session, string $question, SupportCollection $hits): array
    {
        $context = '';
        $citations = [];
        $budget = $this->contextBudget();

        foreach ($hits as $hit) {
            $paper = $hit['paper'];
            $label = "[P{$paper->id}]";

            $block = sprintf("%s %s\n%s\n\n", $label, $paper->title, $hit['chunk']->content);

            // Stop adding context once the budget is spent, rather than
            // sending an over-long prompt that the provider will reject.
            if (mb_strlen($context) + mb_strlen($block) > $budget) {
                break;
            }

            $context .= $block;

            $citations[$paper->id] ??= [
                'paper_id' => $paper->id,
                'label' => $label,
                'title' => $paper->title,
                'snippet' => mb_strimwidth(preg_replace('/\s+/u', ' ', $hit['chunk']->content) ?? '', 0, 200, '…'),
                'chunk_index' => $hit['chunk']->chunk_index,
            ];
        }

        $prompt = <<<PROMPT
        Answer the question using only the excerpts below.

        Cite the source of each claim inline using its bracket label, e.g. [P12].
        If the excerpts do not contain the answer, say so plainly instead of
        guessing.

        EXCERPTS:
        {$context}

        QUESTION: {$question}
        PROMPT;

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $question,
        ]);

        try {
            $answer = $this->ai->generate($prompt, $this->systemInstruction());
        } catch (AiUnavailableException $e) {
            // The question is already recorded; leave the session consistent.
            throw $e;
        }

        // Only report citations the model actually referenced.
        $used = array_values(array_filter(
            $citations,
            fn (array $c) => $this->answerCites($answer, $c['paper_id'])
        ));

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $answer,
            'citations' => $used ?: array_values($citations),
        ]);

        return [
            'answer' => $answer,
            'citations' => $used ?: array_values($citations),
            'session' => $session->refresh(),
        ];
    }

    /**
     * Did the answer cite this paper?
     *
     * The prompt asks for "[P12]", but models reliably drift: gpt-oss emits
     * fullwidth brackets ("\u{3010}P12\u{3011}"), others use parentheses or
     * bold the marker. Matching the literal "[P12]" therefore missed real
     * citations and silently fell back to listing every retrieved source.
     * This matches the label with any surrounding punctuation instead, while
     * still requiring a word boundary so P1 does not match P12.
     */
    private function answerCites(string $answer, int $paperId): bool
    {
        return preg_match('/\bP'.$paperId.'\b/i', $answer) === 1;
    }

    private function sessionFor(User $user, string $scope, ?int $paperId, ?int $collectionId, string $question): ChatSession
    {
        return ChatSession::firstOrCreate(
            [
                'user_id' => $user->id,
                'scope' => $scope,
                'paper_id' => $paperId,
                'collection_id' => $collectionId,
            ],
            ['title' => mb_strimwidth($question, 0, 60, '…')]
        );
    }

    /** Text to summarise: prefer extracted full text, fall back to abstract. */
    private function paperText(Paper $paper): string
    {
        $text = trim((string) ($paper->full_text ?: $paper->abstract));

        return mb_substr($text, 0, $this->contextBudget());
    }

    private function systemInstruction(): string
    {
        return 'You are a careful research assistant. Ground every statement in the '
            .'material you are given. Never invent citations, findings or numbers. '
            .'If the provided material does not answer the question, say so plainly.';
    }
}
