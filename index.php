<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;

// Check if this is a category page request
$requestUri = $_SERVER["REQUEST_URI"] ?? "";
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path ?? "", "/");
$path = trim($path ?? "", "/");

$isCategory = !empty($path) && $path !== "index.php";

if ($isCategory) {

    // This is a category page - include category.php logic inline
    $sectionName = str_replace("_", " ", $path);
    $sectionName = htmlspecialchars($sectionName);

    $yamlFile = __DIR__ . "/links.yaml";
    $config = Yaml::parseFile($yamlFile);

    $links = [];
    $found = false;

    foreach ($config["categories"] as $cat) {
        $urlSlug = strtolower(preg_replace("/[^a-z0-9]+/i", "_", $cat["name"]));
        $urlSlug = trim($urlSlug, "_");
        if ($urlSlug === strtolower($path)) {
            $links = $cat["links"];
            $sectionName = $cat["name"];
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
    }

    // Render category page
    ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars(
        $sectionName,
    ); ?> - Bureau of Organization</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/hyperkinks.css">
    <link rel="stylesheet" href="/tooltips.css">
    <style>
        body { padding: 20px; max-width: 800px; margin: 0 auto; }
        h1 { color: #000066; border-bottom: 2px solid #000066; padding-bottom: 10px; }
        ul { list-style-type: none; padding: 0; }
        li { padding: 8px 0; border-bottom: 1px solid #eeeeee; }
        li:last-child { border-bottom: none; }
        a { color: #0000cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #666; }
    </style>
</head>
<body>
<div class="back-link"><a href="/">&laquo; Back to Bureau of Organization</a></div>
<h1><?php echo htmlspecialchars($sectionName); ?></h1>
<?php if (!empty($links)): ?>
<ul>
<?php foreach ($links as $link): ?>
    <?php if (isset($link["label"]) && $link["label"] !== "More..."): ?>
    <li><a href="<?php echo htmlspecialchars(
        $link["url"] ?? "#",
    ); ?>"><?php echo htmlspecialchars($link["label"]); ?></a></li>
    <?php endif; ?>
<?php endforeach; ?>
</ul>
<?php elseif ($found): ?>
<p>No links in this category yet.</p>
<?php else: ?>
<p>The requested page could not be found.</p>
<?php endif; ?>
</body>
</html>
    <?php exit();
}
function renderLink($item, $separator = "")
{
    $url = isset($item["url"]) ? htmlspecialchars($item["url"]) : "#";
    $label = isset($item["label"]) ? htmlspecialchars($item["label"]) : "";
    $xtra =
        isset($item["xtra"]) && $item["xtra"]
            ? ' <span class="xtra">[Xtra!]</span>'
            : "";
    echo '<a href="' . $url . '">' . $label . "</a>" . $xtra . $separator;
}
function renderLinkList($items, $separator = ", ")
{
    $count = count($items);
    foreach ($items as $i => $item) {
        $sep = $i < $count - 1 ? $separator : "...";
        renderLink($item, $sep);
    }
}
function renderCities($cities, $perRow = 3)
{
    $chunks = array_chunk($cities, $perRow);
    foreach ($chunks as $row) {
        foreach ($row as $i => $city) {
            $sep = $i < count($row) - 1 ? " - " : "";
            renderLink($city, $sep);
        }
        echo "<br>";
    }
}
$yamlFile = __DIR__ . "/links.yaml";
$config = Yaml::parseFile($yamlFile);
if ($config === false) {
    die("Error: Could not parse YAML file\n");
}
$site = $config["site"];
$nav = $config["navigation"];
$highlights = $config["highlights"];
$topLinks = $config["top_links"];
$categories = $config["categories"];
$sidebar = $config["sidebar"];
$worldSites = $config["world_sites"];
$footerLinks = $config["footer"];
$bookmarks = isset($config["bookmarks"]) ? $config["bookmarks"] : [];
function renderBookmarkLink($item, $separator = " | ")
{
    $url = isset($item["url"]) ? htmlspecialchars($item["url"]) : "#";
    $label = isset($item["label"]) ? htmlspecialchars($item["label"]) : "";
    echo '<a href="' . $url . '">' . $label . "</a>";
}
function renderBookmarkLinks($items, $separator = " | ")
{
    $count = count($items);
    foreach ($items as $i => $item) {
        renderBookmarkLink($item);
        if ($i < $count - 1) {
            echo "<span>" . $separator . "</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($site["name"]); ?></title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="personal-bar">
<?php
$personalCount = count($nav["personal"]);
foreach ($nav["personal"] as $i => $item) {
    $sep = $i < $personalCount - 1 ? " | " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="header-bar">
<?php
$headerCount = count($nav["header"]);
foreach ($nav["header"] as $i => $item) {
    $sep = $i < $headerCount - 1 ? " | " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="logo-area">
    <img src="/images/banner.jpg" alt="<?php echo htmlspecialchars(
        $site["name"],
    ); ?>" usemap="#image-map">
</div>

<!-- Image Map Generated by http://www.image-map.net/ -->
<map name="image-map">
    <area target="" alt="What's New on the Web?" title="What's New on the Web?" href="https://www.perplexity.ai/search/what-s-new-on-the-web-Gvuy9wnHRMaJ4g__QsypQg" coords="0,226,125,63" shape="rect">
    <area target="" alt="What's Cool on the Web?" title="What's Cool on the Web?" href="https://www.perplexity.ai/search/what-s-cool-on-the-web-no2fAX1kRXOjxTD6pNmYXg" coords="150,63,284,236" shape="rect">
    <area target="" alt="Today's News" title="Today's News" href="https://ground.news" coords="929,49,1102,243" shape="rect">
    <area target="" alt="More Yahoos" title="More Yahoos" href="https://en.wikipedia.org/wiki/Category:People_pardoned_by_Donald_Trump" coords="1110,50,1263,245" shape="rect">
</map>

<div class="tagline">
<?php
$taglineCount = count($nav["tagline"]);
foreach ($nav["tagline"] as $i => $item) {
    $sep = $i < $taglineCount - 1 ? " - " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="search-bar">
    <form action="#" method="get">
        <input type="text" name="p" value="">
        <select name="t">
            <option value="">Web</option>
            <option value="">Categories</option>
        </select>
        <input type="submit" value="Search">
    </form>
</div>

<div class="main-content">

<div class="highlight-bar">
<?php
$highlightCount = count($highlights);
foreach ($highlights as $i => $item) {
    $sep = $i < $highlightCount - 1 ? " | " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="top-links">
<?php
$topCount = count($topLinks);
foreach ($topLinks as $i => $item) {
    $sep = $i < $topCount - 1 ? " | " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="two-column">
    <div class="left-column">

<?php foreach ($categories as $cat): ?>
        <div class="category">
            <div class="category-header">
                <a href="<?php echo htmlspecialchars(
                    $cat["url"],
                ); ?>"><?php echo htmlspecialchars($cat["name"]); ?></a>
                <?php if (isset($cat["xtra"]) && $cat["xtra"]): ?>
                <span class="xtra">[Xtra!]</span>
                <?php endif; ?>
            </div>
            <div class="category-links">
                <?php renderLinkList($cat["links"]); ?>
            </div>
        </div>
<?php endforeach; ?>

    </div>

    <div class="right-column">

        <div class="sidebar-promo">
            <a href="https://neal.fun"><img src="images/ad-neal-fun.png" alt="Advertisement"/></a>
        </div>

        <div class="sidebar-promo">
            <a href="https://banapana.com"><img src="images/ad-banapana.png" alt="Advertisement"/></a>
        </div>

        <div class="right-section">
            <div class="right-section-header">Featured Sites</div>
<?php foreach ($sidebar["featured"] as $item): ?>
            <?php renderLink($item, "<br>"); ?>
<?php endforeach; ?>
        </div>

        <div class="right-section">
            <div class="right-section-header"><?php echo htmlspecialchars(
                $sidebar["metros"]["title"],
            ); ?></div>
            <a href="#"><?php echo htmlspecialchars(
                $sidebar["metros"]["title"],
            ); ?></a><br>
            <br>
<?php renderCities($sidebar["metros"]["cities"]); ?>
        </div>

    </div>
</div>

</div>

<div class="world-section">
    <strong>World Bureaus</strong><br>
<?php
$worldCount = count($worldSites);
foreach ($worldSites as $i => $item) {
    $sep = $i < $worldCount - 1 ? " - " : "";
    renderLink($item, $sep);
}
?>
</div>

<div class="footer">
    <div class="footer-section">
<?php
$footerCount = count($footerLinks);
foreach ($footerLinks as $i => $item) {
    $sep = $i < $footerCount - 1 ? " | " : "";
    renderLink($item, $sep);
}
?>
    </div>
    <div class="footer-section">
        Copyright &copy; <?php echo htmlspecialchars(
            $site["year"],
        ); ?> <?php echo htmlspecialchars(
     $site["name"],
 ); ?>. All rights reserved.
    </div>
</div>

</body>
</html>
