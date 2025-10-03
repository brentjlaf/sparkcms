# Blog Category Filtering Manual Test

This manual test ensures the blog list hydrator supports posts that provide a primary
`category` string as well as an optional `categories` array containing multiple values.

## Setup
1. Run a local copy of SparkCMS (for example with `php -S localhost:8000` from the
   project root).
2. Navigate to the blog listing page that uses the JavaScript hydrator.

## Scenario
The dataset includes posts that declare multiple categories, such as:

```json
{
  "title": "Designing Accessible Web Experiences",
  "category": "Accessibility",
  "categories": ["Accessibility", "Design"],
  "status": "published"
}
```

## Steps
1. Configure a blog list block with `data-category="design"`.
2. Verify that posts that list `"Design"` inside their `categories` array appear in the
   hydrated list even if their primary `category` is different.
3. Configure a blog list block with `data-category="accessibility"` and confirm the same
   post is still included.
4. Confirm that filtering by an unrelated category (for example `data-category="security"`)
   excludes the post.

## Expected Result
Posts are displayed when any normalized category value—whether from `category` or the
`categories` array—matches the requested filter.
