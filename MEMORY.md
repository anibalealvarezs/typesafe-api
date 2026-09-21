# TypeSafe API Memory
## Scope
- Package role: Communication (SDKs)
- Purpose: This package operates within the Communication (SDKs) layer of the APIs Hub SaaS hierarchy, providing a client interface for the TypeSafe AI (JEV System One) classification and evaluation API.
- Dependency stance: Consumes `anibalealvarezs/api-client-skeleton` and serves the Orchestrator (`apis-hub`) and `apis-hub-facade`.
## Local working rules
- Consult `AGENTS.md` first for package-specific instructions.
- Use this `MEMORY.md` for repository-specific decisions, learnings, and follow-up notes.
- Use `D:\laragon\www\_shared\AGENTS.md` and `D:\laragon\www\_shared\MEMORY.md` for cross-repository protocols and workspace-wide learnings.
- Keep secrets, credentials, tokens, and private endpoints out of this file.
## Current notes
- Communicates with TypeSafe AI System One API (`POST /systemone`).
- Uses `BearerTokenClient` from `api-client-skeleton` for bearer auth and rate limit retry backoff.
- Default model is `jev-latest`.