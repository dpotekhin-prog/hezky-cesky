<?php

namespace App\Service;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Renders docs/USER_GUIDE.{locale}.md — the same files that are read on GitHub — for the in-app help page.
 */
final class UserGuide
{
    public function __construct(
        private readonly CacheInterface $cache,
        #[Autowire('%kernel.project_dir%/docs')]
        private readonly string $docsDir,
    ) {
    }

    public function html(string $locale): string
    {
        $file = sprintf('%s/USER_GUIDE.%s.md', $this->docsDir, 'en' === $locale ? 'en' : 'cs');
        $key = sprintf('user_guide_%s_%d', 'en' === $locale ? 'en' : 'cs', filemtime($file));

        return $this->cache->get($key, static function () use ($file): string {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);

            return (string) $converter->convert(file_get_contents($file));
        });
    }
}
