{{--
    Global viewport utility.

    Include once in the <head> of every page (already wired into layouts/app.blade.php
    and every standalone page head) via: @include('partials.viewport')

    Provides:
    - A single source of truth for the viewport meta tag (device-width + safe-area support
      for notched phones via viewport-fit=cover).
    - Base rules that stop accidental horizontal scroll/overflow on small screens.
    - A live `--vh` CSS custom property fixed to 1% of the *real* window height, so any
      element can use `calc(var(--vh, 1vh) * 100)` instead of `100vh`/`100svh` and stay
      correct even when mobile browser address bars show/hide.
--}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
<style>
    html {
        -webkit-text-size-adjust: 100%;
    }

    html,
    body {
        max-width: 100%;
        overflow-x: hidden;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }
</style>
<script>
    (function () {
        function setCuraViewportUnit() {
            document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
        }

        setCuraViewportUnit();
        window.addEventListener('resize', setCuraViewportUnit);
        window.addEventListener('orientationchange', setCuraViewportUnit);
    })();
</script>
