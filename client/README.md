# Nuxt 3 Minimal Starter

Look at the [Nuxt 3 documentation](https://nuxt.com/docs/getting-started/introduction) to learn more.

## Setup

Make sure to install the dependencies:

```bash
# npm
npm install

# pnpm
pnpm install

# yarn
yarn install

# bun
bun install
```

## Development Server

Start the development server on `http://localhost:3000`:

```bash
# npm
npm run dev

# pnpm
pnpm run dev

# yarn
yarn dev

# bun
bun run dev
```

## Production

Build the application for production:

```bash
# npm
npm run build

# pnpm
pnpm run build

# yarn
yarn build

# bun
bun run build
```

Locally preview production build:

```bash
# npm
npm run preview

# pnpm
pnpm run preview

# yarn
yarn preview

# bun
bun run preview
```

Check out the [deployment documentation](https://nuxt.com/docs/getting-started/deployment) for more information.

## Design tokens and theming

Global design tokens now live in `client/css/app.css` inside the `@theme` block (`--of-*` variables).

- Update colors (surface, text, accent, semantic states) by changing `--of-*` variables only.
- Use Tailwind token aliases in templates: `bg-of-surface`, `text-of-ink`, `border-of-border`, `text-of-accent`, etc.
- Prefer shared semantic classes for layout primitives:
  - `of-page`
  - `of-panel`
  - `of-card`
  - `of-heading`
  - `of-copy`
  - `of-eyebrow`

This contract is intended to keep UI changes centralized so broad restyling can be done without chasing hardcoded page colors.
