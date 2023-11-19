<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

function getContactEmail($url) {
    // The path to your Node.js script
    $scriptPath = 'get_email.js';

    // Call the Node.js script with Puppeteer
    $command = "node $scriptPath " . escapeshellarg($url);
    exec($command, $output, $returnVar);

    // $output will contain the results of the Node.js script
    // If we have $output[0], we need to remove the "mailto:" prefix
    

    return $output[0] ? substr($output[0], 7) : 'N/A';
}

function getAbsoluteUrl($baseUrl, $url) {
    // Check if the URL is already absolute
    if (preg_match("~^(?:f|ht)tps?://~i", $url)) {
        return $url;
    }

    // If not, construct the absolute URL
    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}


function getContactLink($url) {
    try {
        $html = getHtmlContent($url);
        $crawler = new Crawler($html);

        // Find the anchor tag inside a paragraph with class 'heading__phone'
        $contactLinkNode = $crawler->filter('.heading__phone a')->first();
        // Output debug
        echo $contactLinkNode->attr('href') . "\n";
        

        if ($contactLinkNode->count() && strpos($contactLinkNode->attr('href'), 'mailto:') !== false) {
            return $contactLinkNode->attr('href');
        }

        return 'N/A';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}


function getHtmlContent($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        // Add other headers as needed
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        throw new Exception("Unable to retrieve content from $url");
    }

    return $html;
}
function scrapeWhateverData($baseUrl, $pageNumber)
{
    // Tweak with whatever the scheme for paging is for the site.
    $url = $baseUrl . '/?_paged=' . $pageNumber;
    $html = getHtmlContent($url);
    $crawler = new Crawler($html);

    $results = [];

    // Loop through elements containing the data, e.g. <div class="fwpl-result">
    $crawler->filter('.fwpl-result')->each(function (Crawler $node) use (&$results, $baseUrl) {
        $siteName = $node->filter('a')->count() ? $node->filter('a')->text() : 'N/A';
        $detailUrl = $node->filter('a')->count() ? $node->filter('a')->link()->getUri() : '';
        $addressParts = $node->filter('.fwpl-item')->each(function (Crawler $item) {
            return trim($item->text());
        });

        $address = implode(', ', array_filter($addressParts));

        // Convert detail URL to absolute URL
        $detailRelativeUrl = $node->filter('a')->count() ? $node->filter('a')->attr('href') : '';
        $detailUrl = $detailRelativeUrl ? getAbsoluteUrl($baseUrl, $detailRelativeUrl) : '';
        // Output the detail url for debug
        echo $detailUrl . "\n";

        // Get contact link from the detail page for example.
        $contactLink = $detailUrl ? getContactEmail($detailUrl) : 'N/A';
        // Sleep for 1 sec
        sleep(1);

        $results[] = [$siteName, $detailUrl, $address, $contactLink];
    });

    return $results;
}
function writeToCsv($data, $filename = "data.csv")
{
    $file = fopen($filename, 'w');
    fputcsv($file, ['Whatever Name', 'Address', 'Email']);

    foreach ($data as $row) {
        fputcsv($file, $row);
    }

    fclose($file);
}

$baseUrl = ""; // Replace with your URL
$filename = "data.csv";
$file = fopen($filename, 'w');
fputcsv($file, ['Name', 'Detail URL', 'Address', 'Contact Link']);

for ($page = 1; $page <= 2099; $page++) {
    try {
        $pageResults = scrapeWhateverData($baseUrl, $page);
        foreach ($pageResults as $row) {
            fputcsv($file, $row);
        }
    } catch (Exception $e) {
        echo "Error on page $page: " . $e->getMessage() . "\n";
        // Optionally break the loop if a page fails
        // break;
    }
    // Delay for 1 second before processing the next page
    if ($page < 2099) {
        sleep(1);
    }
}

fclose($file);
echo "Data has been written to $filename\n";
