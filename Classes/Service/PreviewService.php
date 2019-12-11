<?php
namespace YoastSeoForTypo3\YoastSeo\Service;

use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class PreviewService
 * @package YoastSeoForTypo3\YoastSeo\Service
 */
class PreviewService
{
    /**
     * Page id
     *
     * @var int
     */
    protected $pageId;

    /**
     * Typoscript config
     *
     * @var array
     */
    protected $config;

    /**
     * Site title
     *
     * @var string
     */
    protected $siteTitle;

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $cObj;

    /**
     * Get preview data
     *
     * @param string $uriToCheck
     * @param int $pageId
     * @param array $config
     * @param string $siteTitle
     * @return false|string
     */
    public function getPreviewData($uriToCheck, $pageId, $config, $siteTitle)
    {
        $this->pageId = $pageId;
        $this->config = $config;
        $this->siteTitle = $siteTitle;
        $this->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        try {
            $content = $this->getContentFromUrl($uriToCheck);
            $data = $this->getDataFromContent($content, $uriToCheck);
        } catch (Exception $e) {
            $data = [
                'error' => [
                    'uriToCheck' => $uriToCheck,
                    'statusCode' => $e->getMessage(),
                ]
            ];
        }
        return json_encode($data);
    }

    /**
     * Get content from url
     *
     * @param $uriToCheck
     * @return null|string
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function getContentFromUrl($uriToCheck): string
    {
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['verify'] = false;
        $report = [];
        $content = GeneralUtility::getUrl(
            $uriToCheck,
            1,
            [
                'X-Yoast-Page-Request' => GeneralUtility::hmac(
                    $uriToCheck
                )
            ],
            $report
        );

        if ((int)$report['error'] === 0) {
            return $content;
        }
        throw new Exception($report['error']);
    }

    /**
     * Get data from content
     *
     * @param string $content
     * @param string $uriToCheck
     * @return array
     */
    protected function getDataFromContent($content, $uriToCheck): array
    {
        $title = $body = $metaDescription = '';
        $locale = 'en';

        $localeFound = preg_match('/<html lang="([a-z]*)"/is', $content, $matchesLocale);
        $titleFound = preg_match("/<title[^>]*>(.*?)<\/title>/is", $content, $matchesTitle);
        $descriptionFound = preg_match(
            "/<meta[^>]*name=[\" | \']description[\"|\'][^>]*content=[\"]([^\"]*)[\"][^>]*>/i",
            $content,
            $matchesDescription
        );
        $bodyFound = preg_match("/<body[^>]*>(.*?)<\/body>/is", $content, $matchesBody);

        if ($bodyFound) {
            $body = $matchesBody[1];

            preg_match_all(
                '/<!--\s*?TYPO3SEARCH_begin\s*?-->.*?<!--\s*?TYPO3SEARCH_end\s*?-->/mis',
                $body,
                $indexableContents
            );

            if (is_array($indexableContents[0]) && !empty($indexableContents[0])) {
                $body = implode($indexableContents[0], '');
            }
        }

        if ($titleFound) {
            $title = $matchesTitle[1];
        }

        if ($descriptionFound) {
            $metaDescription = $matchesDescription[1];
        }

        if ($localeFound) {
            $locale = trim($matchesLocale[1]);
        }
        $url = preg_replace('/\/$/', '', $uriToCheck);
        $baseUrl = preg_replace('/' . preg_quote('/', '/') . '$/', '', $url);

        $faviconSrc = $baseUrl . '/favicon.ico';
        $favIconFound = preg_match('/<link rel=\"shortcut icon\" href=\"(.*)\"/i', $content, $matchesFavIcon);
        if ($favIconFound) {
            $faviconSrc = $matchesFavIcon[1];
        }
        $favIconHeader = @get_headers($faviconSrc);
        if ($favIconHeader[0] === 'HTTP/1.1 404 Not Found') {
            $faviconSrc = '';
        }

        $titlePrependAppend = $this->getPageTitlePrependAppend();
        if ($content !== null) {
            return [
                'id' => $this->pageId,
                'url' => $url,
                'baseUrl' => $baseUrl,
                'slug' => '/',
                'title' => $title,
                'description' => $metaDescription,
                'locale' => $locale,
                'body' => $body,
                'faviconSrc' => $faviconSrc,
                'pageTitlePrepend' => $titlePrependAppend['prepend'],
                'pageTitleAppend' => $titlePrependAppend['append'],
            ];
        }
        return [];
    }

    /**
     * Get page title prepend append
     *
     * @return array
     */
    protected function getPageTitlePrependAppend(): array
    {
        $prependAppend = ['prepend' => '', 'append' => ''];
        $siteTitle = trim($this->siteTitle);
        $pageTitleFirst = (bool)($this->config['pageTitleFirst'] ?? false);
        $pageTitleSeparator = $this->getPageTitleSeparator();
        // only show a separator if there are both site title and page title
        if ($siteTitle === '') {
            $pageTitleSeparator = '';
        } elseif (empty($pageTitleSeparator)) {
            // use the default separator if non given
            $pageTitleSeparator = ': ';
        }

        if ($pageTitleFirst) {
            $prependAppend['append'] = $pageTitleSeparator . $siteTitle;
        } else {
            $prependAppend['prepend'] = $siteTitle . $pageTitleSeparator;
        }

        return $prependAppend;
    }

    /**
     * Get page title separator
     *
     * @return string
     */
    protected function getPageTitleSeparator(): string
    {
        $pageTitleSeparator = '';
        // Check for a custom pageTitleSeparator, and perform stdWrap on it
        if (isset($this->config['pageTitleSeparator'])
            && $this->config['pageTitleSeparator'] !== '') {
            $pageTitleSeparator = $this->config['pageTitleSeparator'];

            if (isset($this->config['pageTitleSeparator.'])
                && is_array($this->config['pageTitleSeparator.'])) {
                $pageTitleSeparator = $this->cObj->stdWrap(
                    $pageTitleSeparator,
                    $this->config['pageTitleSeparator.']
                );
            } else {
                $pageTitleSeparator .= ' ';
            }
        }

        return $pageTitleSeparator;
    }
}
