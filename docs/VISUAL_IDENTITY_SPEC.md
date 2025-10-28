# Visual Identity Specification

## Brand Essence
- **Brand name:** GPT Assistant Boilerplate, positioned as a production-ready AI assistant experience for modern teams.
- **Tagline:** "Conversational intelligence for forward-thinking teams." communicated in the hero subtitle.
- **Value promise:** Deliver a polished, trustworthy assistant that responds instantly, understands context, and accelerates user workflows while keeping analytics and integrations intact.
- **Tone and personality:** Confident, professional, and supportive—reinforced through concise messaging, rounded interface elements, and calm neutral colors accented with an optimistic blue.

## Core Color Palette
| Token | Value | Primary Usage |
| --- | --- | --- |
| `--brand-primary` | `#5f6360` | Headlines, primary text, navigation accents, CTA focus outlines. |
| `--brand-secondary` | `rgb(var(--color_48, 245, 245, 245))` | Header background blocks, feature icon chips, subtle surfaces. |
| `--brand-accent` | `rgb(var(--color_49, 80, 120, 255))` | Primary call-to-action button, focus states, hover transitions. |
| `--brand-background` | `rgb(var(--color_50, 249, 249, 250))` | Page canvas backdrop and feature card backgrounds. |
| `--brand-text` | `rgb(var(--color_51, 38, 38, 38))` | Default body copy and hero titles. |
| `--brand-border` | `rgb(var(--color_52, 208, 208, 210))` | Component outlines, card borders, and layout dividers. |
| `--brand-muted` | `rgba(38, 38, 38, 0.65)` | Secondary messaging, subtitles, footer text. |

### Widget Theme Tokens
The floating assistant widget mirrors the site palette while exposing override-ready CSS variables:
- Primary accent `--chatbot-primary-color: #5f6360` with hover accent `--chatbot-accent-color: rgb(var(--color_49, 80, 120, 255))`.
- Neutral surfaces (`--chatbot-background-color`, `--chatbot-surface-color`) and typography colors (`--chatbot-text-color`, `--chatbot-muted-color`) align with the page.
- Structural tokens (`--chatbot-border-color`, `--chatbot-border-radius`, `--chatbot-shadow`, `--chatbot-spacing`) define container borders, 16px rhythm, and elevated surfaces.

## Typography
- **Primary typeface stack:** Arial, Helvetica, helvetica-w01-bold, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif.
- **Body copy:** 16px size with 1.5 line height for readability.
- **Hero title:** 48px/1.2 line height, bold weight to command attention (drops to 40px on tablets).
- **Hero subtitle:** 20px, regular weight with muted color for supporting tone.
- **Section headings:** 36px weight 600 for section titles; card headings use 24px.
- **Navigation & CTA text:** Medium weight (500–600) reinforcing clarity and emphasis.
- **Widget typography:** Defaults to 14px base with layout modifiers for compact (13px) and spacious (15px) modes while inheriting the same font family.

## Layout & Spacing
- **Grid system:** Twelve-column responsive grid with 24px gutters (16px on tablet) guiding hero, feature, and overview sections.
- **Spacing rhythm:** 32px vertical padding for section wrappers, with hero using 64px top padding and CTA/button spacing tuned via the 16px token.
- **Corner radii:** Rounded treatments (12–24px) on icons, cards, and buttons create a friendly, approachable feel; CTA uses a pill-shaped 999px radius.
- **Elevation:** Cards and floating widget use soft drop shadows (up to `0 16px 32px rgba(0, 0, 0, 0.08)` on hero card and `var(--chatbot-shadow)` on widget) to separate from the background without harsh contrast.
- **Responsiveness:** Breakpoints at 1024px and 768px collapse grid columns into full-width stacks while adjusting typography and padding for smaller viewports.

## Iconography & Imagery
- **Brand iconography:** Line-based 24×24 SVG icons with 2px strokes, rounded caps, and outlines matching the primary brand color.
- **Illustration:** Hero card features `/assets/assistant-avatar.png`, framed with a 24px radius and subtle secondary border to emphasize the assistant persona.
- **Assistant avatar usage:** Shared between the marketing page and widget configuration to maintain continuity.

## Calls to Action & Interactive States
- **Primary CTA:** Filled button with accent background, white text, and hover treatment using `color-mix` to lighten the blue plus a slight upward translate.
- **Focus indicators:** High-contrast outlines (3px for page CTAs/nav, 2px for widget buttons) ensure accessibility across keyboard interactions.
- **Widget trigger:** Floating circular toggle adopts the primary color, scaling to 105% on hover while retaining consistent drop shadow.

## Content Guidelines
- Messaging highlights extendability, accessibility, analytics, and secure file handling. Supportive paragraphs emphasize cohesive design, trust, and workflow acceleration.
- Overview aside lists assistant highlights, while the footer reiterates themes of clarity, performance, and innovation.

## Assistant Widget Integration
- Default initialization pairs the visual identity with widget options: floating mode, bottom-right positioning, matching primary color/background/font family, and shared avatar asset.
- Optional proactive reveal introduces the assistant automatically after five seconds unless the user has already interacted, ensuring the assistant remains top-of-mind without overwhelming the layout.

