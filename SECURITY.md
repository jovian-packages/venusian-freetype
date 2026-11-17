# Security Policy

## Supported versions

Venusian is pre-1.0. No 0.x release receives security fixes or advisories; fixes land in the
next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on this repository's
**Security** tab. If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, and steps to reproduce. Reports are read and
weighed for the release line in development; before 1.0 there is no response-time commitment.

## Security model

- Font files are parsed by FreeType, in C, through ext-freetype. This package parses no font bytes itself: a malformed font is FreeType's to refuse, and its error code is passed on in a `TypographyException`.
- A font file is opened read-only, from the path a `FontSource` names. Paths come from the application or its font catalog, never from the text being set.
- Nothing is written, and nothing is read but the font files asked for.
- Open faces are held until the driver's `release()`. An application that opens faces from untrusted paths without releasing holds one FreeType face a source.
