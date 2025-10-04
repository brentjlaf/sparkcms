# PageRepository manual verification

Because Composer cannot reach Packagist in this environment (`curl error 56`), PHPUnit could not be installed.
Use the following steps to validate draft persistence and history retrieval end-to-end:

1. **Prepare sample page data**
   - Edit `CMS/data/pages.json` and ensure there is a page with a known numeric `id` (e.g., `3`).

2. **Save a draft**
   - Send a POST request to `/liveed/save-draft.php` with form data `id=3`, `content=Draft preview`, and `timestamp=<current unix timestamp>`.
   - Expect a JSON response containing `{ "ok": true, "revision": "...", "timestamp": ... }`.
   - Confirm that `CMS/data/drafts/page-3.json` contains the provided content, timestamp, and a matching `revision` value.

3. **Load the draft**
   - Request `/liveed/load-draft.php?id=3`.
   - Verify the JSON response echoes the draft content, timestamp, and revision stored in the previous step.

4. **Publish content**
   - Send a POST request to `/liveed/save-content.php` with `id=3`, the `revision` token from step 2 (to simulate a fresh page reload), and a new `content` payload.
   - Confirm the JSON response reports `ok: true` with a new `revision`, and that the draft file `CMS/data/drafts/page-3.json` has been removed.

5. **Review history**
   - Request `/liveed/get-history.php?id=3&limit=5`.
   - Ensure the response JSON includes the most recent `updated content` entry for the page and that each entry now provides a `revision` identifier.

These checks exercise the repository-backed endpoints for saving drafts, loading drafts, persisting content, and fetching history.
