# JRN Solar website

Website for jrnsolar.com. Static HTML; no build step required.

## Pages

- `index.html`: Home
- `services.html`: Services
- `about.html`: About JRN Solar
- `quote.html`: Quote request

The quote form currently prepares an SMS. It does not submit email or store leads.

## Hostinger deployment

The four HTML files belong directly in the domain's `public_html` folder.
Keep mailbox passwords and other server-only secrets outside Git.
GitHub and Hostinger deployment still need to be connected before pushes update production.
