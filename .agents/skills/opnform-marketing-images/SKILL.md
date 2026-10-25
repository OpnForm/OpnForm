---
name: opnform-marketing-images
description: Create consistent workflow illustrations for OpnForm marketing pages, including integrations and features. Use for generated product illustrations, not screenshots of actual UI.
---

# OpnForm marketing illustrations

Create illustrations that explain a concrete product use case and belong to the same visual family across pages.

## Ground the concept

- Read the page content and inspect the relevant implementation before choosing the scene. Show behavior OpnForm actually supports; distinguish native integrations from actions performed by an external automation platform.
- Choose one useful scenario per illustration. Show the input, its destination, and the result using a small number of cards and clear connectors. Avoid a generic collage of logos.
- If the user supplies a style reference, inspect it and use it as a visual reference when generating. Otherwise inspect an existing illustration, such as [Slack](../../../client/public/img/pages/integrations/slack-hero.webp) or [Google Sheets](../../../client/public/img/pages/integrations/google-sheets-hero.webp).

## Compose the generation request

Describe the scene in this order, adapting it to the page rather than copying an old prompt:

1. **Purpose and format:** a standalone illustration for the page, usually landscape at 1536 × 1024 to match existing integration images.
2. **Scenario:** the respondent's task, the essential form fields, and the concrete outcome in the connected tool.
3. **Composition:** the cards, their relative positions, the direction of the connectors, and the few labels needed to understand the flow.
4. **Visual language:** the shared style below, plus restrained provider accents when useful.
5. **Constraints:** the content and visual mistakes to avoid for this particular scene.

Keep the stable visual language consistent while varying the scenario and composition. Reference images establish style; they do not establish product behavior.

## Shared visual language

- White or very light neutral canvas, near-black geometric sans-serif text, and OpnForm blue `#2563EB` for primary actions and connections.
- Fine borders, restrained rounded corners, subtle shadows, generous margins, and an optional pale-blue dot grid.
- Purposeful flat interface cards with clear visual hierarchy. Use short, legible labels in the page's language; current integration pages use English.
- Provider colors and recognizable marks may identify the destination, while OpnForm remains visually consistent.
- Keep the page heading, navigation, and surrounding layout in the webpage. Do not reproduce them inside the illustration.
- Avoid device or browser frames, traffic-light controls, decorative 3D objects, broad purple gradients, watermarks, fabricated metrics, and testimonials.
- Treat illustrated interfaces as explanatory mockups. Use actual screenshots when the user needs proof of the real interface or exact setup instructions.

## Generate, inspect, and integrate

Use the available image-generation tool and follow its reference-image and editing instructions. For a series, establish the style with one representative asset before applying it to the remaining scenes.

Inspect each result for readable text, accurate branding, sensible connectors, correct field mapping, and consistency with the implementation. Correct visual or factual mistakes before integrating it; a successful generation call is not a quality check.

Preserve the original generated asset outside the tracked source tree. Export an optimized WebP into the relevant existing directory under `client/public/img/`, following neighboring filenames. Check that text remains readable at the rendered size and on narrow screens. Supply descriptive alt text and explicit image dimensions in the page.

Verify that the final asset loads in the local page and that the surrounding content still reads well.

## What belongs in Git

Commit the final web assets, page changes, and reusable strategy updates. Keep per-image prompts, generation transcripts, temporary contact sheets, machine-specific paths, and run manifests in ignored local scratch space unless the user explicitly requests their delivery. Do not add a prompt JSON alongside public assets or marketing content.

Update this skill when the visual strategy changes; do not turn it into an archive of prompts or individual generation runs.
