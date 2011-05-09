<?php

require_once dirname(__FILE__) . '/config.php';


function filter($a, $fn) {
    return array_filter($a, $fn);
}

function map($a, $fn) {
    return array_map($fn, $a);
}

function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES, "UTF-8");
}

function starts($string, $prefix) {
    return strpos($string, $prefix) === 0;
}

function contains($string, $needle) {
    return strpos($string, $needle) !== false;
}

function renderTemplate($___path, $___data) {
    extract($___data);
    ob_start();
    require $___path;
    return ob_get_clean();
}

function hasClass($node, $class) {
    $classes = str_word_count($node->getAttribute('class'), 1);
    return in_array($class, $classes);
}

function remove($node) {
    $node->parentNode->removeChild($node);
}

function nodes($dom, $name) {
    $nodes = array();

    foreach ($dom->getElementsByTagName($name) as $node) {
        $nodes[] = $node;
    }

    return $nodes;
}

function centerImage($img) {
    $clone = $img->cloneNode(true);
    $div = new DOMElement('div');
    $img->parentNode->replaceChild($div, $img);
    $div->setAttribute('class', 'image');
    $div->appendChild($clone);
}

function download($what, $where) {
    if (is_file($where)) return;

    echo "Downloading: $what... ";

    $data = file_get_contents($what);

    if ($data) {
        file_put_contents($where, $data);
        echo "OK\n";
    } else {
        echo "ERROR\n";
    }
}

function processImages($page) {
    foreach (nodes($page, 'img') as $img) {
        if (! hasClass($img, 'explanation')) {
            remove($img);
            continue;
        }

        $imageSrc = $img->getAttribute('src');
        $imgName = pathinfo(parse_url($imageSrc, PHP_URL_PATH), PATHINFO_BASENAME);

        download($imageSrc, BUILD_DIR . '/images/'.$imgName);

        $imageSrc = str_replace('http://learnyousomeerlang.com/static/img', '../images', $imageSrc);
        $img->setAttribute('src', $imageSrc);

        centerImage($img);
    }
}

function removeHyperlink($link) {
    $nodes = $link->childNodes;
    $parent = $link->parentNode;
    $parent->removeChild($link);

    foreach ($nodes as $node) {
        $parent->appendChild($node);
    }
}

function removeNoscript($dom2) {
    foreach (nodes($dom2, 'div') as $div) {
        if (hasClass($div, 'noscript')) {
            $div->parentNode->removeChild($div);
        }
    }
}

function removeNavigation($dom2) {
    foreach (nodes($dom2, 'ul') as $ul) {
        if (hasClass($ul, 'navigation')) {
            $ul->parentNode->removeChild($ul);
        }
    }
}

function prepareHref($link) {
    $href = $link->getAttribute('href');
    $href = str_replace('http://learnyousomeerlang.com/', '', $href);

    if (contains($href, '#')) {
        if (!starts($href, '#')) {
            $href = str_replace('#', '.html#', $href);
        }
    } else {
        $href = $href . '.html';
    }

    $link->setAttribute('href', $href);
}

function processLinks($dom2) {
    foreach (nodes($dom2, 'a') as $link) {
        if (!$link->hasAttribute('href')) {
            continue;
        }

        if (!hasClass($link, 'chapter') && !hasClass($link, 'section')) {
            removeHyperlink($link);
        }

        if (hasClass($link, 'chapter')) {
            prepareHref($link);
        }
    }
}

function changeLayout($pageHtml) {
    $original = new DOMDocument;
    $modified = new DOMDocument;

    $original->loadHTML($pageHtml);
    $content = $original->getElementById('content');

    $modified->loadHTML(renderTemplate(dirname(__FILE__) . '/templates/page-layout.html', array(
        'title' => EBOOK_TITLE,
    )));

    $content = $modified->importNode($content, true);
    $modified->getElementsByTagName('body')->item(0)->appendChild($content);

    return $modified;
}

function removeComingSoon($dom) {
    foreach (nodes($dom, '*') as $node) {
        if (hasClass($node, 'coming_soon')) {
            $node->parentNode->removeChild($node);
        }
    }
}

function processPage($pageHtml) {
    $pageDom = changeLayout($pageHtml);

    processImages($pageDom);
    processLinks($pageDom);
    removeNoscript($pageDom);
    removeNavigation($pageDom);
    removeComingSoon($pageDom);

    return $pageDom->saveHTML();
}

function extractSections($h3) {
    $node = $h3->nextSibling;
    while (strtolower($node->nodeName) !== 'ul') {
        $node = $node->nextSibling;
    }

    $lis = nodes($node, 'li');

    $sections = array();
    foreach ($lis as $li) {
        $href = $li->getElementsByTagName('a')->item(0)->getAttribute('href');
        $fragment = parse_url($href, PHP_URL_FRAGMENT);

        $sections[] = (object) array(
            'name' => $li->textContent,
            'url'  => "#$fragment",
        );
    }
    return $sections;
}

function extractPages($url) {
    $dom = new DOMDocument;
    $dom->loadHTMLFile($url);

    download($url, BUILD_DIR . '/downloaded-pages/toc.html');

    $h3s = nodes($dom, 'h3');

    foreach ($h3s as $h3) {
        $links = $h3->getElementsByTagName('a');

        if ($links->length) {
            $pages[] = (object) array(
                'name'     => $h3->textContent,
                'url'      => $links->item(0)->getAttribute('href'),
                'sections' => extractSections($h3),
            );
        }
    }

    return $pages;
}

function downloadPages($pages) {
    map($pages, function ($page) {
        $urlPath = parse_url($page->url, PHP_URL_PATH);
        $fileName = BUILD_DIR . "/downloaded-pages/$urlPath.html";

        download($page->url, $fileName);
    });
}

function createBuildDirs() {
    $dirs = array(
        BUILD_DIR . '/css',
        BUILD_DIR . '/pages',
        BUILD_DIR . '/images',
        BUILD_DIR . '/downloaded-pages',
    );

    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function processPages() {
    $files = scandir(BUILD_DIR . '/downloaded-pages');
    $pages = filter($files, function ($file) {
        return $file !== '.' && $file !== '..';
    });

    foreach ($pages as $page) {
        echo "Processing page: $page...\n";
        $html = file_get_contents(BUILD_DIR . '/downloaded-pages/' . $page);
        $processed = processPage($html);
        file_put_contents(BUILD_DIR . '/pages/' . $page, $processed);
    }
}

function buildTocNcx($pages) {
    $data = array(
        'title'     => EBOOK_TITLE,
        'author'    => EBOOK_AUTHOR,
        'navPoints' => map($pages, function ($page) {
            $fileName = pathinfo($page->url, PATHINFO_FILENAME);

            return (object) array(
                'id'    => $fileName,
                'label' => $page->name,
                'src'   => "pages/$fileName.html",
            );
        }),
    );

    $ncx = renderTemplate(dirname(__FILE__) . '/templates/toc.ncx', $data);
    file_put_contents(BUILD_DIR . '/toc.ncx', $ncx);
    return $ncx;
}

function buildOpf($pages) {
    $data = array(
        'title'    => EBOOK_TITLE,
        'author'   => EBOOK_AUTHOR,
        'language' => EBOOK_LANGUAGE,
        'items'    => map($pages, function ($page) {
            $fileName = pathinfo($page->url, PATHINFO_FILENAME);

            return (object) array(
                'id'        => $fileName,
                'mediaType' => 'application/xhtml+xml',
                'href'      => "pages/$fileName.html",
            );
        }),
    );

    $opf = renderTemplate(dirname(__FILE__) . '/templates/book.opf', $data);
    file_put_contents(BUILD_DIR . '/book.opf', $opf);
    return $opf;
}

function copyStaticFiles() {
    copy(dirname(__FILE__) . '/css/main.css', BUILD_DIR . '/css/main.css');
    copy(dirname(__FILE__) . '/images/cover.jpg', BUILD_DIR . '/images/cover.jpg');
    copy(dirname(__FILE__) . '/images/cc.png', BUILD_DIR . '/images/cc.png');
    copy(dirname(__FILE__) . '/templates/preamble.html', BUILD_DIR . '/pages/preamble.html');
    copy(dirname(__FILE__) . '/templates/license.html', BUILD_DIR . '/pages/license.html');
}

function addOwnPages($pages) {
    $frontPages = array(
        (object) array( 'url' => 'preamble' ),
        (object) array( 'url' => 'license'  ),
        (object) array( 'url' => 'toc'      ),
    );

    $pages = array_merge($frontPages, $pages);

    return $pages;
}

function main() {
    createBuildDirs();

    $pages = extractPages('http://learnyousomeerlang.com/content');

    downloadPages($pages);
    buildTocNcx($pages);

    $pages = addOwnPages($pages);
    buildOpf($pages);

    processPages();
    copyStaticFiles();
}

main();
