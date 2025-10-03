# PageRepository manual verification

Because Composer cannot reach Packagist in this environment (`curl error 56`), PHPUnit could not be installed.
Use the following steps to validate draft persistence and history retrieval end-to-end:

1. **Prepare sample page data**
   - Edit `CMS/data/pages.json` and ensure there is a page with a known numeric `id` (e.g., `3`).

2. **Save a draft**
   - Send a POST request to `/liveed/save-draft.php` with form data `id=3`, `content=Draft preview`, and `timestamp=<current unix timestamp>`.
   - Expect the response body `OK`.
   - Confirm that `CMS/data/drafts/page-3.json` contains the provided content and timestamp.

3. **Load the draft**
   - Request `/liveed/load-draft.php?id=3`.
   - Verify the JSON response echoes the draft content and timestamp stored in the previous step.

4. **Publish content**
   - Send a POST request to `/liveed/save-content.php` with `id=3` and a new `content` payload.
   - Confirm the response `OK` and that the draft file `CMS/data/drafts/page-3.json` has been removed.

5. **Review history**
   - Request `/liveed/get-history.php?id=3&limit=5`.
   - Ensure the response JSON includes the most recent `updated content` entry for the page.

These checks exercise the repository-backed endpoints for saving drafts, loading drafts, persisting content, and fetching history.
