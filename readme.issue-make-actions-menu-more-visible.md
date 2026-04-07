# Issue: Make Actions Menu More Visible

Replace the overflow/three-dot menu with inline action buttons in the control bar to improve discoverability of Export, Create Alert, Add Log Entry, and Share View features.

## UX Research: Hidden Menus vs Visible Buttons

Research consistently shows that moving actions from hidden menus into visible UI significantly increases engagement and discoverability.

### Nielsen Norman Group (2016) — Quantitative Study

-   **179 participants** across 6 websites, desktop and mobile.
-   Hidden nav used in **27%** of cases vs **48%** for visible navigation — discoverability nearly **cut in half**.
-   On mobile: hidden nav **57%** vs combo nav **86%** (1.5x difference).
-   Hidden nav took **5-7 seconds longer** to discover on desktop.
-   Task difficulty ratings **+21% worse** with hidden navigation.
-   Task completion dropped **-20%** with hidden menus.
-   Source: [Hamburger Menus and Hidden Navigation Hurt UX Metrics](https://www.nngroup.com/articles/hamburger-menus/)

### Spotify (2016) — Hamburger to Tab Bar

-   Replaced hamburger sidebar with bottom tab bar in iOS app.
-   Overall clicks **+9%**.
-   **Menu item clicks +30%**.
-   No negative impact on retention, engagement, or consumption time.
-   Source: [TechCrunch — Spotify ditches the hamburger menu](https://techcrunch.com/2016/05/03/spotify-ditches-the-controversial-hamburger-menu-in-ios-app-redesign/)

### Luke Wroblewski (ex-Google) — "Obvious Always Wins" (2015)

Multiple case studies all showing the same pattern:

-   **Facebook iOS:** Hamburger to tab bar — engagement up across several metrics.
-   **Zeebox:** Tabs to hamburger — engagement **fell drastically**. Tab bar had driven 55% average weekly frequency.
-   **Redbooth:** Hamburger to tab bar — sessions and users increased.
-   **Polar App:** Toggle menu "looked cleaner" but engagement plummeted because sections were now hidden.
-   Source: [LukeW — Obvious Always Wins](https://www.lukew.com/ff/entry.asp?1945)

### NN/g — Overflow/Contextual Menu Research

-   Options in overflow menus are **less discoverable** than visible options.
-   Users may **not realize** additional options exist.
-   Contextual menu icons often **missed** when lacking visual prominence.
-   Source: [Designing Effective Contextual Menus: 10 Guidelines](https://www.nngroup.com/articles/contextual-menus-guidelines/)

### Mobile Commerce Study

-   Abandonment rates **+75%** when key actions (Add to Cart, Checkout) were in overflow menus.
-   Source: [Overflowing with Problems: The Case Against Overflow Menus](https://www.bomberbot.com/ux/overflowing-with-problems-the-case-against-overflow-menus-in-user-interface-design/)

### NN/g — Menu Design Checklist (2024)

> "The hamburger menu (or any form of hiding navigation categories under a single menu) is not appropriate for desktop websites and apps. **Out of sight means out of mind.**"

-   Source: [Menu-Design Checklist: 17 UX Guidelines](https://www.nngroup.com/articles/menu-design/)

### Summary Table

| Source   | Metric                                 | Finding                |
| -------- | -------------------------------------- | ---------------------- |
| NN/g     | Desktop nav usage (hidden vs visible)  | 27% vs 48% (nearly 2x) |
| NN/g     | Mobile nav usage (hidden vs combo)     | 57% vs 86% (1.5x)      |
| Spotify  | Menu item clicks after showing tab bar | +30%                   |
| Spotify  | Overall clicks                         | +9%                    |
| NN/g     | Task difficulty with hidden nav        | +21% worse             |
| NN/g     | Task completion with hidden nav        | -20%                   |
| Commerce | Abandonment from overflow menus        | +75%                   |
| NN/g     | Time-to-navigation penalty (desktop)   | +5-7 seconds           |

### Quotable for Blog/Docs

-   "Discoverability is cut almost in half by hiding a website's main navigation." — NN/g
-   "Obvious always wins." — Luke Wroblewski
-   "Out of sight means out of mind." — NN/g (2024)
