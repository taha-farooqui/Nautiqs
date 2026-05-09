{{--
    Google Translate Element wired up invisibly.

    HOW IT WORKS
    - The widget needs a #google_translate_element div in the DOM. We render
      it but hide it with CSS — we drive translation programmatically through
      our own switcher buttons (header dropdown + settings).
    - The widget reads the user's preferred language from the `googtrans`
      cookie. Setting `googtrans=/en/fr` switches to French, `/en/en` reverts.
    - We seed the cookie before the script loads so the page renders in the
      right language on first paint (no English flash).
    - On <html> we set lang="en" because the widget needs to know the SOURCE
      language. The displayed language is controlled entirely via the cookie.

    GOTCHAS
    - Google injects a 40px-tall toolbar at the top of <body>. We hide it via
      CSS (top: 0 !important; display: none).
    - body gets margin-top: 40px applied by Google. We override that too.
    - Fonts can shift slightly on first translation. Acceptable trade-off.
--}}

@once
    {{-- Tell the BROWSER (Chrome / Edge) not to show its built-in
         "Translate this page" banner. We do our own translation via the
         Google Translate widget below; the browser's banner would just
         flash a duplicate. --}}
    <meta name="google" content="notranslate">

    <style>
        /* Hide Google's default UI completely — we use our own switcher. */
        .goog-te-banner-frame,
        .goog-te-banner-frame.skiptranslate,
        .skiptranslate iframe,
        .goog-te-gadget,
        #goog-gt-tt,
        .goog-te-balloon-frame,
        iframe.goog-te-banner-frame { display: none !important; visibility: hidden !important; }
        body { top: 0 !important; position: static !important; }

        /* The hidden mount point. */
        #google_translate_element { display: none; }

        /* Stop Google from styling translated text differently. */
        font[style*="vertical-align: inherit"] { background: transparent !important; box-shadow: none !important; }

        /* Anti-flash: hide body until translation has actually finished, or
           a 3000ms safety net fires. We watch the DOM for Google's swap
           (it injects <font> tags around translated text) and reveal when
           it's done. */
        html.translating body { visibility: hidden !important; }
    </style>

    <script>
        (function () {
            var current = document.cookie.split('; ').find(function (c) { return c.indexOf('googtrans=') === 0; });
            var lang = 'fr';
            if (current) {
                var parts = current.split('=')[1].split('/');
                if (parts[2]) lang = parts[2];
            } else {
                document.cookie = 'googtrans=/en/' + lang + '; path=/';
                document.cookie = 'googtrans=/en/' + lang + '; path=/; domain=.' + window.location.hostname;
            }

            // Only hide the body when we'll actually translate. English needs
            // no work, so we leave it visible for instant paint.
            if (lang !== 'en') {
                document.documentElement.classList.add('translating');
            }

            window.__nautiqsLocale = lang;
        })();

        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,fr',
                autoDisplay: false,
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');

            // Watch for the first <font> tag Google injects (signals the
            // translation has actually started rendering), then reveal.
            var revealed = false;
            var reveal = function () {
                if (revealed) return;
                revealed = true;
                document.documentElement.classList.remove('translating');
            };

            var observer = new MutationObserver(function () {
                if (document.querySelector('font[style*="vertical-align"]')) {
                    // Give Google one more frame to finish the rest of the page.
                    setTimeout(reveal, 50);
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // Hard failsafe — never lock the user out longer than 3s even
            // if Google doesn't load (network blocked, ad blocker, etc.).
            setTimeout(reveal, 3000);
        }

        /**
         * Public API used by our switcher (header + settings).
         */
        window.setNautiqsLocale = function (lang) {
            if (lang !== 'en' && lang !== 'fr') return;
            document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=.' + window.location.hostname;
            document.cookie = 'googtrans=/en/' + lang + '; path=/';
            document.cookie = 'googtrans=/en/' + lang + '; path=/; domain=.' + window.location.hostname;
            window.location.reload();
        };
    </script>

    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>

    <div id="google_translate_element"></div>
@endonce
