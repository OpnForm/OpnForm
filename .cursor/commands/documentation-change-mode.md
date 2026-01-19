# change-documentation: Change Documentation Mode

### General

When I say "enter change documentation mode" or "document changes", analyze the recent changes in the codebase and generate a comprehensive markdown document directly in the chat. The document should be a "techincal spec" document of changes to be done (as we'll use it in the context of another project where this change doesn't exist yet). Document should include:

1. A sort summary of the changes detected + rationale. Don't mention commit, mention them as changes to be done as the AI thay will apply the change doens't have context of this current project
2. Explanations of each file modified with before/after code snippets
3. Clear checklist of changes
4. Finish with a clear instruction (1 sentence enough) prompting the AI in the other project to apply similar changes 1 by 1 in current project right now (so that we can copy and paste the whole block without change). Also explain that to find file they can: search by name or if doesn't work search by some code (variables, html etc) to find similar files which may have different name. Because in anothe project file names may be slightly different.

### Mode parameteres

If I specify "for commit X" or "since commit X", focus on changes from that specific commit or range. Use git diff in command line to get the diff. For instance `git --no-pager show HEAD` or `git --no-pager show [commit-id]`.

If I specify "for current changes", analyze uncommitted changes in the working directory.

If I specify "for file X", focus only on changes to that specific file.s

### Code changes

For each changed function or code block, keep it as short as possible but include:

- The file path
- The function/block name
- Before and after code snippets
- Very short explanation of what changed and why

VERY Important: for change-documentation mode, do not execute any commands or create files - just provide the RAW markdown code (escaped within a code block ```) for the doc directly in the chat.
