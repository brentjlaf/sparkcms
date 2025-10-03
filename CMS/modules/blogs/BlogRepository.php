<?php
// File: BlogRepository.php
// Repository for handling blog post data and derived information such as categories.

require_once __DIR__ . '/../../includes/data.php';

class BlogRepository
{
    /**
     * @var string
     */
    private $postsFile;

    /**
     * @var array|null
     */
    private $postsCache = null;

    /**
     * @var array|null
     */
    private $categoriesCache = null;

    /**
     * @param string|null $postsFile Optional path to the posts JSON file.
     */
    public function __construct($postsFile = null)
    {
        if ($postsFile === null) {
            $postsFile = __DIR__ . '/../../data/blog_posts.json';
        }
        $this->postsFile = $postsFile;
    }

    /**
     * Read blog posts as an array of associative arrays.
     *
     * @return array
     */
    public function readPosts()
    {
        if ($this->postsCache === null) {
            $data = read_json_file($this->postsFile);
            if (!is_array($data)) {
                $data = [];
            }

            $normalized = [];
            foreach ($data as $post) {
                if (is_array($post)) {
                    $normalized[] = $post;
                }
            }

            $this->postsCache = $normalized;
        }

        return $this->postsCache;
    }

    /**
     * List unique, non-empty categories derived from the posts data.
     *
     * @return array
     */
    public function listCategories()
    {
        if ($this->categoriesCache === null) {
            $unique = [];
            foreach ($this->readPosts() as $post) {
                if (!is_array($post)) {
                    continue;
                }
                $category = isset($post['category']) ? $post['category'] : null;
                if (is_string($category)) {
                    $category = trim($category);
                    if ($category !== '') {
                        $unique[$category] = true;
                    }
                }
            }
            $this->categoriesCache = array_values(array_keys($unique));
        }

        return $this->categoriesCache;
    }
}
