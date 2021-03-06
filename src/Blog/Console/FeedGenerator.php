<?php
/**
 * @license http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @copyright Copyright (c) Matthew Weier O'Phinney
 */

namespace Mwop\Blog\Console;

use DateTime;
use Mni\FrontYAML\Bridge\CommonMark\CommonMarkParser;
use Mni\FrontYAML\Parser;
use Mwop\Blog;
use Symfony\Component\Yaml\Parser as YamlParser;
use Traversable;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Feed\Writer\Feed as FeedWriter;
use ZF\Console\Route;

class FeedGenerator
{
    use RoutesTrait;

    private $authors = [];

    private $authorsPath;

    private $console;

    private $defaultAuthor = [
        'name'  => 'Matthew Weier O\'Phinney',
        'email' => 'me@mwop.net',
        'uri'   => 'http://mwop.net',
    ];

    private $mapper;

    private $renderer;

    private $router;

    public function __construct(
        Blog\MapperInterface $mapper,
        RouterInterface $router,
        TemplateRendererInterface $renderer,
        string $authorsPath
    ) {
        $this->mapper      = $mapper;
        $this->router      = $this->seedRoutes($router);
        $this->renderer    = $renderer;
        $this->authorsPath = $authorsPath;
    }

    public function __invoke(Route $route, Console $console) : int
    {
        $this->console = $console;
        $outputDir = $route->getMatchedParam('outputDir');
        $baseUri   = $route->getMatchedParam('baseUri');

        $this->console->writeLine('Generating base feeds');
        $this->generateFeeds(
            $outputDir . '/',
            $baseUri,
            'Blog entries :: phly, boy, phly',
            'blog',
            'blog.feed',
            [],
            $this->mapper->fetchAll()
        );

        $cloud = $this->mapper->fetchTagCloud();
        $tags  = array_map(function ($item) {
            return $item->getTitle();
        }, iterator_to_array($cloud->getItemList()));

        foreach ($tags as $tag) {
            if (empty($tag)) {
                continue;
            }

            $this->console->writeLine('Generating feeds for tag ' . $tag);
            $this->generateFeeds(
                sprintf('%s/%s.', $outputDir, $tag),
                $baseUri,
                sprintf('Tag: %s :: phly, boy, phly', $tag),
                'blog.tag',
                'blog.tag.feed',
                ['tag' => $tag],
                $this->mapper->fetchAllByTag($tag)
            );
        }

        return 0;
    }

    private function generateFeeds(
        string $fileBase,
        string $baseUri,
        string $title,
        string $landingRoute,
        string $feedRoute,
        array $routeOptions,
        Traversable $posts
    ) {
        foreach (['atom', 'rss'] as $type) {
            $this->generateFeed($type, $fileBase, $baseUri, $title, $landingRoute, $feedRoute, $routeOptions, $posts);
        }
    }

    private function generateFeed(
        string $type,
        string $fileBase,
        string $baseUri,
        string $title,
        string $landingRoute,
        string $feedRoute,
        array $routeOptions,
        Traversable $posts
    ) {
        $routeOptions['type'] = $type;

        $landingUri = $baseUri . $this->generateUri($landingRoute, $routeOptions);
        $feedUri    = $baseUri . $this->generateUri($feedRoute, $routeOptions);

        $feed = new FeedWriter();
        $feed->setTitle($title);
        $feed->setLink($landingUri);
        $feed->setFeedLink($feedUri, $type);

        if ($type === 'rss') {
            $feed->setDescription($title);
        }

        $parser = new Parser(null, new CommonMarkParser());
        $latest = false;
        $posts->setCurrentPageNumber(1);
        foreach ($posts as $details) {
            $document = $parser->parse(file_get_contents($details['path']));
            $post     = $document->getYAML();
            $html     = $document->getContent();
            $author   = $this->getAuthor($post['author']);

            if (! $latest) {
                $latest = $post;
            }

            $entry = $feed->createEntry();
            $entry->setTitle($post['title']);
            // $entry->setLink($baseUri . $this->generateUri('blog.post', ['id' => $post['id']]));
            $entry->setLink($baseUri . sprintf('/blog/%s.html', $post['id']));

            $entry->addAuthor($author);
            $entry->setDateModified(new DateTime($post['updated']));
            $entry->setDateCreated(new DateTime($post['created']));
            $entry->setContent($this->createContent($html, $post));

            $feed->addEntry($entry);
        }

        // Set feed date
        $feed->setDateModified(new DateTime($latest['updated']));

        // Write feed to file
        $file = sprintf('%s%s.xml', $fileBase, $type);
        $file = str_replace(' ', '+', $file);
        file_put_contents($file, $feed->export($type));
    }

    /**
     * Retrieve author metadata.
     *
     * @param string $author
     * @return string[]
     */
    private function getAuthor(string $author) : array
    {
        if (isset($this->authors[$author])) {
            return $this->authors[$author];
        }

        $path = sprintf('%s/%s.yml', $this->authorsPath, $author);
        if (! file_exists($path)) {
            $this->authors[$author] = $this->defaultAuthor;
            return $this->authors[$author];
        }

        $this->authors[$author] = (new YamlParser())->parse(file_get_contents($path));
        return $this->authors[$author];
    }

    /**
     * Normalize generated URIs.
     *
     * @param string $route
     * @param array $options
     * @return string
     */
    private function generateUri(string $route, array $options) : string
    {
        $uri = $this->router->generateUri($route, $options);
        return str_replace('[/]', '', $uri);
    }

    /**
     * Create feed content.
     *
     * Renders h-entry data for the feed and appends it to the HTML markup content.
     *
     * @param string $content
     * @param array $post
     * @return string
     */
    private function createContent(string $content, array $post) : string
    {
        $view   = new Blog\EntryView($post);
        $hEntry = $this->renderer->render('blog::hcard', $view);
        return sprintf("%s\n\n%s", $content, $hEntry);
    }
}
