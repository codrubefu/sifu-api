# Sifu API — Claude entry point

Read [AGENTS.md](AGENTS.md) first. It is the shared entry point for architecture,
navigation, invariants, test commands, and context-budget guidance.

Use [docs/AI_GUIDE.md](docs/AI_GUIDE.md) to locate the relevant module and tests.
Search headings and read only relevant sections of `docs/project-rules-agent.md`
and `docs/functionality-explainer-agent.md`. Read `docs/deployment-security.md`
for deployment/security changes. Full-document reads are appropriate for full audits.

The optional `.claude/agents/sifu-api-dev.md` agent follows the same sources.
Do not delegate automatically just to reread documentation. For behavior changes,
update the touched functionality documentation and OpenAPI; run targeted tests first.
