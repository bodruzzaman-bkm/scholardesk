<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\MemberRole;
use App\Enums\NotificationType;
use App\Enums\PaperSource;
use App\Enums\ReadingStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Comment;
use App\Models\Highlight;
use App\Models\InAppNotification;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Report;
use App\Models\Tag;
use App\Models\User;
use App\Services\IndexingService;
use Database\Seeders\Support\DemoPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Populates a deployment with a library that can be shown to somebody.
 *
 * A fresh deployment is an empty login page: whoever opens the URL registers
 * an account and finds nothing, which demonstrates none of the twenty-two
 * requirements. This seeds a researcher whose library exercises all four
 * modules at once — papers with real PDFs and real indexes, tags, shared
 * collections, threaded comments, an activity feed, notifications, and a
 * report waiting in the admin queue.
 *
 * Whether it runs at all is the deployment's decision, not this class's:
 * docker/entrypoint.sh calls it only when DEMO_SEED is true, so a deployment
 * that never sets it is untouched, and so is the test suite. Invoking it by
 * hand always seeds, which is what an explicit `db:seed` should do.
 *
 * It is idempotent regardless, because the entrypoint runs on every container
 * start. The demo researcher's presence is the marker: on the second and
 * every later run this returns immediately, so a restart neither duplicates
 * the library nor discards edits made to it during a demonstration.
 *
 * It is not a fixture for tests. Tests build exactly the rows they assert on;
 * this builds a room that looks lived in.
 */
class DemoSeeder extends Seeder
{
    /**
     * Shared by all three demo accounts, and printed at the end of the run so
     * whoever deploys can read it out of the logs.
     */
    public const PASSWORD = 'demo1234';

    private const ADMIN_EMAIL = 'admin@scholardesk.demo';

    private const RESEARCHER_EMAIL = 'researcher@scholardesk.demo';

    private const COLLABORATOR_EMAIL = 'collaborator@scholardesk.demo';

    public function __construct(private IndexingService $indexer) {}

    public function run(): void
    {
        if (User::where('email', self::RESEARCHER_EMAIL)->exists()) {
            $this->command?->info('Demo data is already present — nothing to do.');

            return;
        }

        [$admin, $researcher, $collaborator] = $this->users();

        $tags = $this->tags($researcher);
        $papers = $this->papers($researcher, $tags);
        $collections = $this->collections($researcher, $papers);

        $this->share($collections, $researcher, $collaborator, $admin);
        $this->discuss($collections, $papers, $researcher, $collaborator, $admin);
        $this->announce($researcher);

        $this->command?->info(sprintf(
            'Demo data seeded: %d papers, %d collections, %d tags. Sign in as %s / %s.',
            count($papers),
            count($collections),
            count($tags),
            self::RESEARCHER_EMAIL,
            self::PASSWORD,
        ));
    }

    /**
     * @return array{User, User, User}
     */
    private function users(): array
    {
        // The admin is made here rather than promoted afterwards because
        // registration deliberately cannot produce one — the role is not a
        // field the sign-up form accepts.
        $admin = User::create([
            'name' => 'Dr. Ayesha Rahman',
            'email' => self::ADMIN_EMAIL,
            'password' => self::PASSWORD,
            'role' => UserRole::Administrator,
        ]);

        $researcher = User::create([
            'name' => 'Tanvir Ahmed',
            'email' => self::RESEARCHER_EMAIL,
            'password' => self::PASSWORD,
            'role' => UserRole::Researcher,
        ]);

        // Exists so that sharing has somebody to share with, and so the
        // activity feed has a second name in it.
        $collaborator = User::create([
            'name' => 'Nusrat Jahan',
            'email' => self::COLLABORATOR_EMAIL,
            'password' => self::PASSWORD,
            'role' => UserRole::Researcher,
        ]);

        return [$admin, $researcher, $collaborator];
    }

    /**
     * @return array<string, Tag>
     */
    private function tags(User $owner): array
    {
        $palette = [
            'Transformers' => '#6366F1',
            'Computer vision' => '#0EA5E9',
            'Foundational' => '#F59E0B',
            'Generative' => '#10B981',
            'Optimisation' => '#8B5CF6',
            'To discuss' => '#EF4444',
        ];

        $tags = [];

        foreach ($palette as $name => $color) {
            $tags[$name] = Tag::create([
                'name' => $name,
                'color' => $color,
                'user_id' => $owner->id,
            ]);
        }

        return $tags;
    }

    /**
     * Create each paper, write it a real PDF, and run the genuine indexer over
     * it so semantic search and the AI panels have something to retrieve.
     *
     * @param  array<string, Tag>  $tags
     * @return array<string, Paper>
     */
    private function papers(User $owner, array $tags): array
    {
        $papers = [];

        foreach ($this->library() as $key => $spec) {
            $slug = Str::of($spec['title'])->slug()->limit(60, '')->value();
            $path = 'papers/'.$slug.'-'.Str::random(8).'.pdf';

            $document = DemoPdf::render(
                $spec['title'],
                $spec['authors'].' · '.$spec['venue'].' '.$spec['year'],
                $spec['body'],
            );

            Storage::disk('public')->put($path, $document['bytes']);

            $paper = Paper::create([
                'title' => $spec['title'],
                'authors' => $spec['authors'],
                'year' => $spec['year'],
                'venue' => $spec['venue'],
                'abstract' => $spec['body'][0],
                'doi' => $spec['doi'],
                'source' => PaperSource::Doi,
                'file_path' => $path,
                'reading_status' => $spec['status'],
                'user_id' => $owner->id,
            ]);

            // Backdated so the dashboard's "papers added per month" chart has
            // a shape instead of one bar. created_at is not fillable, and the
            // timestamps must be silenced or save() would overwrite them.
            $added = now()->subDays($spec['added']);
            $paper->timestamps = false;
            $paper->forceFill(['created_at' => $added, 'updated_at' => $added])->save();
            $paper->timestamps = true;

            $paper->tags()->attach(collect($spec['tags'])->map(fn ($t) => $tags[$t]->id));

            // The real pipeline: parse the PDF, chunk it, embed each chunk.
            // Nothing here fakes an index — a seeded paper is retrievable for
            // exactly the reason an uploaded one is.
            $this->indexer->index($paper);

            if (isset($spec['note'])) {
                Note::create([
                    'paper_id' => $paper->id,
                    'user_id' => $owner->id,
                    'content' => $spec['note'],
                ]);
            }

            if (isset($spec['highlight'])) {
                $this->highlight($paper, $owner, $document['lines'], $spec['highlight']);
            }

            $papers[$key] = $paper;
        }

        return $papers;
    }

    /**
     * Place a highlight over the line that actually contains the phrase.
     *
     * @param  list<array{page: int, text: string, left: float, top: float, width: float, height: float}>  $lines
     * @param  array{phrase: string, color: string, note: string}  $spec
     */
    private function highlight(Paper $paper, User $owner, array $lines, array $spec): void
    {
        $line = collect($lines)->first(
            fn (array $l) => str_contains(Str::lower($l['text']), Str::lower($spec['phrase']))
        );

        // The phrase is expected to be there, but a reworded summary should
        // degrade to no highlight rather than to a rectangle over nothing.
        if ($line === null) {
            return;
        }

        Highlight::create([
            'paper_id' => $paper->id,
            'user_id' => $owner->id,
            'text' => $line['text'],
            'note' => $spec['note'],
            'color' => $spec['color'],
            'position' => [
                'page' => $line['page'],
                'rects' => [[
                    'left' => $line['left'],
                    'top' => $line['top'],
                    'width' => $line['width'],
                    'height' => $line['height'],
                ]],
            ],
        ]);
    }

    /**
     * @param  array<string, Paper>  $papers
     * @return array<string, Collection>
     */
    private function collections(User $owner, array $papers): array
    {
        $groups = [
            'attention' => [
                'name' => 'Attention & language models',
                'description' => 'The line from the original Transformer through to retrieval-augmented generation. Reading list for the literature review chapter.',
                'papers' => ['attention', 'bert', 'gpt3', 'rag'],
            ],
            'vision' => [
                'name' => 'Computer vision baselines',
                'description' => 'The architectures every vision result is still measured against.',
                'papers' => ['alexnet', 'resnet', 'vit'],
            ],
            'generative' => [
                'name' => 'Generative models',
                'description' => 'Adversarial and diffusion approaches, kept together for the comparison section.',
                'papers' => ['gan', 'diffusion'],
            ],
        ];

        $collections = [];

        foreach ($groups as $key => $group) {
            $collection = Collection::create([
                'name' => $group['name'],
                'description' => $group['description'],
                'user_id' => $owner->id,
            ]);

            $collection->papers()->attach(
                collect($group['papers'])->map(fn (string $p) => $papers[$p]->id)
            );

            // The creator's own membership row. Collection::roleFor() treats
            // the owner as an owner regardless, but the members list on the
            // sharing screen reads from this table.
            CollectionMember::create([
                'collection_id' => $collection->id,
                'user_id' => $owner->id,
                'role' => MemberRole::Owner,
            ]);

            $collections[$key] = $collection;
        }

        return $collections;
    }

    /**
     * Hand two collections to other people, at different roles, and log it.
     *
     * @param  array<string, Collection>  $collections
     */
    private function share(array $collections, User $owner, User $collaborator, User $admin): void
    {
        $grants = [
            ['attention', $collaborator, MemberRole::Editor],
            ['attention', $admin, MemberRole::Viewer],
            ['generative', $collaborator, MemberRole::Viewer],
        ];

        foreach ($grants as [$key, $user, $role]) {
            CollectionMember::create([
                'collection_id' => $collections[$key]->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);

            Activity::create([
                'collection_id' => $collections[$key]->id,
                'user_id' => $owner->id,
                'type' => ActivityType::MemberAdded,
                'metadata' => ['name' => $user->name],
            ]);

            InAppNotification::create([
                'user_id' => $user->id,
                'type' => NotificationType::Share,
                'message' => sprintf('%s shared “%s” with you.', $owner->name, $collections[$key]->name),
                'link' => '/collections/'.$collections[$key]->id,
                'is_read' => false,
            ]);
        }

        // A few more feed entries so the activity panel is not only memberships.
        Activity::create([
            'collection_id' => $collections['attention']->id,
            'user_id' => $collaborator->id,
            'type' => ActivityType::PaperAdded,
            'metadata' => ['title' => 'Retrieval-Augmented Generation for Knowledge-Intensive NLP Tasks'],
        ]);

        Activity::create([
            'collection_id' => $collections['attention']->id,
            'user_id' => $owner->id,
            'type' => ActivityType::StatusChanged,
            'metadata' => ['title' => 'Language Models are Few-Shot Learners'],
        ]);
    }

    /**
     * Threaded comments, plus one flagged so the admin report queue is not
     * empty when the moderation screen is opened.
     *
     * @param  array<string, Collection>  $collections
     * @param  array<string, Paper>  $papers
     */
    private function discuss(array $collections, array $papers, User $owner, User $collaborator, User $admin): void
    {
        $root = Comment::create([
            'collection_id' => $collections['attention']->id,
            'user_id' => $owner->id,
            'content' => 'I have put the four papers in publication order. If we are framing the review around retrieval, RAG probably belongs first rather than last — thoughts?',
        ]);

        Comment::create([
            'collection_id' => $collections['attention']->id,
            'user_id' => $collaborator->id,
            'parent_id' => $root->id,
            'content' => 'Agreed. Chronological order buries the actual argument. I would open with RAG and use the 2017 paper as background.',
        ]);

        Activity::create([
            'collection_id' => $collections['attention']->id,
            'user_id' => $collaborator->id,
            'type' => ActivityType::CommentAdded,
        ]);

        $onPaper = Comment::create([
            'paper_id' => $papers['attention']->id,
            'user_id' => $collaborator->id,
            'content' => 'The positional encoding section is worth reading twice — it is the part everyone skips and then misremembers.',
        ]);

        Comment::create([
            'paper_id' => $papers['attention']->id,
            'user_id' => $owner->id,
            'parent_id' => $onPaper->id,
            'content' => 'Noted. I have highlighted the relevant paragraph in the reader.',
        ]);

        // Something for the moderator to actually decide about. Reported by
        // the researcher, still open, so the queue shows a pending item.
        $flagged = Comment::create([
            'paper_id' => $papers['gpt3']->id,
            'user_id' => $collaborator->id,
            'content' => 'buy cheap essays cheapessays dot example dot com fast delivery guaranteed grades',
        ]);

        Report::create([
            'user_id' => $owner->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $flagged->id,
            'reason' => 'Spam — advertising an essay mill, and it is academic misconduct as well as off topic.',
            'status' => ReportStatus::Open,
        ]);

        InAppNotification::create([
            'user_id' => $admin->id,
            'type' => NotificationType::System,
            'message' => 'A comment has been reported and is waiting in the moderation queue.',
            'link' => '/admin/reports',
            'is_read' => false,
        ]);
    }

    /** A couple of read and unread notifications, so the badge has a number. */
    private function announce(User $researcher): void
    {
        InAppNotification::create([
            'user_id' => $researcher->id,
            'type' => NotificationType::Comment,
            'message' => 'Nusrat Jahan replied to your comment on “Attention & language models”.',
            'link' => '/collections',
            'is_read' => false,
        ]);

        InAppNotification::create([
            'user_id' => $researcher->id,
            'type' => NotificationType::AiDone,
            'message' => '“Deep Residual Learning for Image Recognition” is indexed and ready to search.',
            'link' => '/papers',
            'is_read' => true,
        ]);
    }

    /**
     * The library.
     *
     * The metadata is the real bibliographic record for each paper. The body
     * text is not: it is a summary written for this seed, because the
     * published abstracts are the authors' copyright and the PDFs are not
     * ours to redistribute. Every generated document says as much on its face
     * — it is a summary sheet, and reads like one.
     *
     * @return array<string, array<string, mixed>>
     */
    private function library(): array
    {
        return [
            'attention' => [
                'title' => 'Attention Is All You Need',
                'authors' => 'Ashish Vaswani, Noam Shazeer, Niki Parmar, Jakob Uszkoreit, Llion Jones, Aidan N. Gomez, Lukasz Kaiser, Illia Polosukhin',
                'year' => 2017,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.1706.03762',
                'status' => ReadingStatus::Read,
                'tags' => ['Transformers', 'Foundational'],
                'added' => 158,
                'body' => [
                    'Introduces the Transformer, a sequence model that discards recurrence and convolution entirely and relies on self-attention alone to relate positions in a sequence. Because every position attends to every other in a single step, the path a signal travels between two tokens is constant rather than growing with the distance between them, which is what made long-range dependencies hard for recurrent models to learn.',
                    'Scaled dot-product attention scores each query against every key, divides by the square root of the key dimension to keep the softmax away from its saturated region, and uses the resulting weights to average the values. Multi-head attention runs several of these in parallel over separately learned projections, so different heads can specialise in different relations without competing for one set of weights.',
                    'Because the architecture is order-agnostic, positional information is added to the input embeddings as sinusoids of geometrically spaced frequencies, which lets the model attend by relative offset. Training parallelises across positions in a way recurrent models cannot, and the result set new translation scores at a fraction of the training cost.',
                ],
                'note' => "## Why this is first in the review\n\nEverything after 2017 in this collection is a variation on this architecture. The **decoder-only** simplification is what GPT takes; the **encoder-only** half is what BERT takes.\n\nWorth re-reading: the positional encoding argument, which is the part I keep having to look up again.",
                'highlight' => [
                    'phrase' => 'self-attention alone',
                    'color' => '#FDE68A',
                    'note' => 'This is the sentence the whole architecture follows from.',
                ],
            ],
            'bert' => [
                'title' => 'BERT: Pre-training of Deep Bidirectional Transformers for Language Understanding',
                'authors' => 'Jacob Devlin, Ming-Wei Chang, Kenton Lee, Kristina Toutanova',
                'year' => 2019,
                'venue' => 'NAACL',
                'doi' => '10.18653/v1/N19-1423',
                'status' => ReadingStatus::Read,
                'tags' => ['Transformers', 'Foundational'],
                'added' => 141,
                'body' => [
                    'Takes the encoder half of the Transformer and pre-trains it on unlabelled text, then fine-tunes the same weights for individual tasks. The contribution is the training objective rather than the architecture: earlier language models read left to right, so a token could only be conditioned on what preceded it.',
                    'Masked language modelling hides a fraction of the input tokens and asks the model to recover them from both directions at once, which is what makes the representation genuinely bidirectional. A second objective, next-sentence prediction, trains the model to judge whether two segments actually followed one another, giving it a handle on relationships that span a sentence boundary.',
                    'The practical consequence is that a single pre-trained checkpoint, fine-tuned briefly, beat task-specific architectures across a broad benchmark suite. It established the pre-train then fine-tune pattern that the field then used for several years.',
                ],
                'highlight' => [
                    'phrase' => 'both directions at once',
                    'color' => '#BFDBFE',
                    'note' => 'The contrast with left-to-right models — cite this when introducing masked objectives.',
                ],
            ],
            'gpt3' => [
                'title' => 'Language Models are Few-Shot Learners',
                'authors' => 'Tom B. Brown, Benjamin Mann, Nick Ryder, Melanie Subbiah, Jared Kaplan, Prafulla Dhariwal, Arvind Neelakantan, and others',
                'year' => 2020,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.2005.14165',
                'status' => ReadingStatus::Reading,
                'tags' => ['Transformers'],
                'added' => 96,
                'body' => [
                    'Scales an autoregressive Transformer to 175 billion parameters and reports that many tasks can be performed without any gradient update at all, given only a task description and a handful of examples placed in the prompt. The paper calls this in-context learning, and treats it as a property that emerges with scale rather than something trained for directly.',
                    'The evaluation is deliberately structured around zero-shot, one-shot and few-shot settings, and compares them against fine-tuned baselines. Performance improves smoothly with model size across most tasks, but the gap to fine-tuning does not close everywhere, and tasks requiring careful multi-step reasoning remain weak.',
                    'The limitations section is unusually candid about what scaling did not fix: the model still contradicts itself over long passages, absorbs the biases of its training corpus, and gives no signal about when it is wrong. Those three are the openings that later retrieval and alignment work aims at.',
                ],
                'note' => "Read up to section 4. The **few-shot framing** is the part relevant to us — the scaling curves are context, not the argument.\n\n- [x] Sections 1–3\n- [ ] Section 5, limitations\n- [ ] Broader impacts",
            ],
            'rag' => [
                'title' => 'Retrieval-Augmented Generation for Knowledge-Intensive NLP Tasks',
                'authors' => 'Patrick Lewis, Ethan Perez, Aleksandra Piktus, Fabio Petroni, Vladimir Karpukhin, Naman Goyal, and others',
                'year' => 2020,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.2005.11401',
                'status' => ReadingStatus::Reading,
                'tags' => ['Transformers', 'To discuss'],
                'added' => 61,
                'body' => [
                    'Couples a pre-trained generator with a retriever over an external document index, so that the facts a model states come from documents fetched at inference time rather than from weights fixed at training time. The knowledge store can be edited or replaced without retraining anything, which is the property that makes the approach practical.',
                    'The retriever encodes the query and the passages into a shared vector space and returns the nearest documents; the generator is then conditioned on the query together with those passages. Because the retrieved passages are identifiable, an answer can cite the evidence it used, which a parametric model cannot do.',
                    'This is the pattern ScholarDesk implements. A paper is chunked with overlap, each chunk embedded, and a question embedded the same way; the closest chunks by cosine similarity are retrieved and those chunks, and only those, are given to the language model as context.',
                ],
                'highlight' => [
                    'phrase' => 'cite the evidence it used',
                    'color' => '#BBF7D0',
                    'note' => 'The grounding claim. This is exactly what our AI panels do — reference it in the methodology.',
                ],
            ],
            'resnet' => [
                'title' => 'Deep Residual Learning for Image Recognition',
                'authors' => 'Kaiming He, Xiangyu Zhang, Shaoqing Ren, Jian Sun',
                'year' => 2016,
                'venue' => 'CVPR',
                'doi' => '10.1109/CVPR.2016.90',
                'status' => ReadingStatus::Read,
                'tags' => ['Computer vision', 'Foundational'],
                'added' => 173,
                'body' => [
                    'Addresses a degradation problem rather than an overfitting one: past a certain depth, adding layers made training error worse, which cannot be explained by capacity. The paper argues that the difficulty is optimisation, and that it is easier for a stack of layers to learn a residual correction than the underlying mapping itself.',
                    'The residual block adds an identity shortcut around each pair of convolutions, so a block that has nothing useful to contribute can settle at zero rather than having to reproduce its input. Gradients reach early layers through those shortcuts largely undiminished, which is what makes very deep networks trainable at all.',
                    'With this change the authors trained networks an order of magnitude deeper than was previously practical and won the 2015 ImageNet classification task. The shortcut connection has since become near-universal, including in the Transformer blocks elsewhere in this collection.',
                ],
            ],
            'alexnet' => [
                'title' => 'ImageNet Classification with Deep Convolutional Neural Networks',
                'authors' => 'Alex Krizhevsky, Ilya Sutskever, Geoffrey E. Hinton',
                'year' => 2012,
                'venue' => 'NeurIPS',
                'doi' => '10.1145/3065386',
                'status' => ReadingStatus::Read,
                'tags' => ['Computer vision', 'Foundational'],
                'added' => 179,
                'body' => [
                    'The result that moved computer vision from engineered features to learned ones. A deep convolutional network trained on ImageNet cut the top-five error rate far below the best feature-engineering pipelines of the time, and did so on two consumer GPUs.',
                    'Several ingredients mattered together rather than singly: rectified linear units, which train several times faster than saturating alternatives; dropout in the fully connected layers to control overfitting; aggressive data augmentation by translation, reflection and colour jitter; and a split across two GPUs that was as much a memory constraint as a design choice.',
                    'Its influence is mostly historical now, but every later vision baseline is positioned relative to it, which is why it stays in this collection.',
                ],
            ],
            'vit' => [
                'title' => 'An Image is Worth 16x16 Words: Transformers for Image Recognition at Scale',
                'authors' => 'Alexey Dosovitskiy, Lucas Beyer, Alexander Kolesnikov, Dirk Weissenborn, Xiaohua Zhai, and others',
                'year' => 2021,
                'venue' => 'ICLR',
                'doi' => '10.48550/arXiv.2010.11929',
                'status' => ReadingStatus::ToRead,
                'tags' => ['Computer vision', 'Transformers'],
                'added' => 44,
                'body' => [
                    'Applies a standard Transformer encoder directly to images by cutting each image into fixed-size patches, embedding them linearly, and treating the resulting sequence exactly as a sentence of tokens. Almost nothing is changed from the language architecture, which is the point being made.',
                    'Convolutional networks build in assumptions about locality and translation equivariance. A Transformer has neither, and on mid-sized datasets it therefore loses to a comparable convolutional model. Given a large enough pre-training corpus, the learned attention patterns overtake the built-in priors and the ordering reverses.',
                    'The conclusion is about inductive bias and data scale rather than about architecture: the hand-designed prior is worth a great deal when data is scarce and progressively less as it becomes plentiful.',
                ],
            ],
            'gan' => [
                'title' => 'Generative Adversarial Networks',
                'authors' => 'Ian J. Goodfellow, Jean Pouget-Abadie, Mehdi Mirza, Bing Xu, David Warde-Farley, Sherjil Ozair, Aaron Courville, Yoshua Bengio',
                'year' => 2014,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.1406.2661',
                'status' => ReadingStatus::Read,
                'tags' => ['Generative', 'Foundational'],
                'added' => 166,
                'body' => [
                    'Frames generative modelling as a two-player game. A generator maps noise to samples and a discriminator tries to tell those samples from real data; the generator is trained on the discriminator gradient, so its objective is supplied by another learned model rather than by a hand-written likelihood.',
                    'The theoretical result is that the game has a unique global optimum at which the generated distribution equals the data distribution and the discriminator is everywhere uncertain. In practice the two networks must be kept in rough balance, and training is notoriously unstable when they are not.',
                    'Avoiding an explicit likelihood is what let the approach produce sharp samples where likelihood-based models of the period produced blurry ones. It also removed the training signal that would have said how well it was doing, which is the trade later diffusion work revisits.',
                ],
            ],
            'diffusion' => [
                'title' => 'Denoising Diffusion Probabilistic Models',
                'authors' => 'Jonathan Ho, Ajay Jain, Pieter Abbeel',
                'year' => 2020,
                'venue' => 'NeurIPS',
                'doi' => '10.48550/arXiv.2006.11239',
                'status' => ReadingStatus::ToRead,
                'tags' => ['Generative'],
                'added' => 9,
                'body' => [
                    'Defines a fixed forward process that adds Gaussian noise to data over many small steps until nothing but noise remains, and trains a network to invert one step of it. Sampling then starts from noise and walks the reverse process back to a data point.',
                    'The training objective reduces to predicting the noise that was added at a given step, which turns generative modelling into a plain regression problem with a stable loss. That stability is the practical contrast with adversarial training, where there is no such quantity to watch.',
                    'The cost is at sampling time: the reverse process needs many network evaluations where an adversarial generator needs one. Sample quality was competitive enough that the trade was accepted, and reducing the step count became its own research direction.',
                ],
            ],
            'adam' => [
                'title' => 'Adam: A Method for Stochastic Optimization',
                'authors' => 'Diederik P. Kingma, Jimmy Ba',
                'year' => 2015,
                'venue' => 'ICLR',
                'doi' => '10.48550/arXiv.1412.6980',
                'status' => ReadingStatus::Read,
                'tags' => ['Optimisation'],
                'added' => 120,
                'body' => [
                    'A first-order optimiser that keeps running exponential averages of both the gradient and its square, and uses their ratio to give every parameter its own effective step size. Parameters with consistently small or noisy gradients are moved further than a single global learning rate would move them.',
                    'Both averages start at zero and are therefore biased towards it early in training, so the method divides each by the remaining mass of its decay schedule. Without that correction the first few hundred updates are far too small, which is the detail most re-implementations get wrong.',
                    'It is included here as the default almost every other paper in this library trained with, and as the reason their learning-rate settings are not comparable to older work.',
                ],
            ],
            'dropout' => [
                'title' => 'Dropout: A Simple Way to Prevent Neural Networks from Overfitting',
                'authors' => 'Nitish Srivastava, Geoffrey Hinton, Alex Krizhevsky, Ilya Sutskever, Ruslan Salakhutdinov',
                'year' => 2014,
                'venue' => 'Journal of Machine Learning Research',
                'doi' => '10.5555/2627435.2670313',
                'status' => ReadingStatus::ToRead,
                'tags' => ['Optimisation', 'Foundational'],
                'added' => 88,
                'body' => [
                    'Randomly removes units, along with their connections, on each training pass. Because no unit can rely on any particular other unit being present, the network cannot build the brittle co-adaptations that fit noise in the training set.',
                    'The paper offers a second reading of the same procedure: training a network with dropout approximates training an ensemble of exponentially many thinned networks that share weights, and scaling the weights at test time approximates averaging their predictions.',
                    'It was for several years the standard regulariser for fully connected layers. Batch normalisation and larger datasets have displaced it in many settings, but the ensemble argument is still the clearest account of why it works.',
                ],
            ],
            'lstm' => [
                'title' => 'Long Short-Term Memory',
                'authors' => 'Sepp Hochreiter, Jürgen Schmidhuber',
                'year' => 1997,
                'venue' => 'Neural Computation',
                'doi' => '10.1162/neco.1997.9.8.1735',
                'status' => ReadingStatus::Read,
                'tags' => ['Foundational'],
                'added' => 185,
                'body' => [
                    'Diagnoses why recurrent networks fail to learn long-range structure: the gradient is multiplied by the same recurrent weight at every step of the backward pass, so it shrinks or grows geometrically with the length of the dependency, and either way carries no usable signal.',
                    'The remedy is a memory cell whose internal state passes forward through an additive path with no repeated multiplication, guarded by multiplicative gates that decide what is written, what is retained and what is read out. Gradients travel that path without the geometric decay.',
                    'It held the field for two decades and is the baseline the Transformer paper is arguing against. Reading it first makes the constant path length claim in that paper mean something specific rather than merely sound good.',
                ],
            ],
        ];
    }
}
