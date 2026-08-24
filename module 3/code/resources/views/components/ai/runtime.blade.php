{{--
    Shared browser runtime for the AI cards.

    Included once per page (via @once) by any of the ai.* components, so
    Summary, Chat and Related can each be dropped in independently without
    duplicating this script.
--}}
@once
    @push('scripts')
    <script>
        window.scholardeskAi = {
            csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

            /**
             * POST JSON and return the parsed body, or throw with a message
             * that is safe to show the user.
             */
            async post(url, payload) {
                let res;
                try {
                    res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify(payload || {}),
                    });
                } catch (_) {
                    throw new Error('Could not reach the server. Try again.');
                }

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    // 422 carries field errors; 503 carries a readable reason.
                    const detail = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.error || data.message);
                    throw new Error(detail || 'Something went wrong. Your library is unaffected.');
                }

                return data;
            },

            /**
             * Minimal markdown -> HTML.
             *
             * The input is model output, so it is escaped BEFORE any tags are
             * introduced; no model response can inject markup.
             */
            markdown(md) {
                const esc = (s) => String(s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                return esc(md)
                    .replace(/^#{3,} (.*)$/gm, '<h4>$1</h4>')
                    .replace(/^## (.*)$/gm, '<h3>$1</h3>')
                    .replace(/^# (.*)$/gm, '<h3>$1</h3>')
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.+?)\*/g, '<em>$1</em>')
                    .replace(/`(.+?)`/g, '<code>$1</code>')
                    .replace(/^[-*] (.*)$/gm, '<li>$1</li>')
                    .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>')
                    .replace(/\n{2,}/g, '</p><p>')
                    .replace(/^(?!<[hupl])/, '<p>') + '</p>';
            },
        };
    </script>
    @endpush
@endonce
