<style>
        :root { --bg:#fff; --fg:#212529; --muted:#6c757d; --line:#dee2e6; --bad:#b02a37; --warn:#997404; --ok:#146c43; --info:#087990; }
        @media (prefers-color-scheme: dark) { :root { --bg:#16181b; --fg:#e4e6e8; --muted:#9aa1a8; --line:#33373b; --bad:#ea868f; --warn:#ffda6a; --ok:#75b798; --info:#6edff6; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        main { max-width: 64rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.2rem; margin: 2rem 0 .5rem; }
        h3 { font-size: 1rem; margin: 1.25rem 0 .25rem; }
        .muted { color: var(--muted); } .bad { color: var(--bad); font-weight: 600; } .warn { color: var(--warn); font-weight: 600; }
        .ok { color: var(--ok); } .info { color: var(--info); } .small { font-size: .875rem; } .num { text-align: right; white-space: nowrap; }
        .scroll { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: .5rem 0 1rem; }
        th, td { border: 1px solid var(--line); padding: .35rem .6rem; text-align: left; vertical-align: top; }
        th { font-weight: 600; } td.num, th.num { text-align: right; }
        ul { margin: .25rem 0 1rem 1.25rem; padding: 0; }
        p.note { margin: 2rem 0 0; }
</style>
