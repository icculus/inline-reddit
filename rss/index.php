<?php
require_once('./rss_php.php');

// !!! FIXME: Block every request until a single feed pull from
// !!! FIXME:  reddit is cached, instead of everyone pulling from reddit
// !!! FIXME:  until someone manages to rename to $fname.
function verify_cache($fname, $url, $maxage)
{
    $origerrorlevel = error_reporting();
    error_reporting(0);  // filemtime(), etc, output warnings for missing file.
    $retval = true;
    $rc = filemtime($fname);
    if ( ($rc === false) || (($rc + $maxage) < time()) )
    {
        $outfname = "cache-" . getmypid();
        $ch = curl_init($url);
        $fp = fopen($outfname, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $retval = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        if ($retval)
            $retval = rename($outfname, $fname);
        else
            unlink($outfname);
    } // if

    error_reporting($origerrorlevel);
    return $retval;
} // verify_cache



// Mainline!


// We only want to talk to Reddit's servers once every X seconds at most, as
//  each individual RSS download ends up taking several seconds, so we'll live
//  with slightly outdated results to make this site more responsive.
$cachefname = 'cached-rss.rss';
if (!verify_cache($cachefname, 'http://www.reddit.com/.rss', 60))
{
    header('HTTP/1.0 503 Service unavailable');
    header('Connection: close');
    header('Content-Type: text/plain');
    print("\n\nCouldn't get the reddit RSS feed. Try again later.\n\n");
    exit(0);
} // if

header('Content-Type: text/xml; charset=UTF-8');

$rss = new rss_php;
$rss->load($cachefname);
$items = $rss->getItems();

foreach ($items as $index => $item)
{
    $desc = $item['description'];
print($item['title'] . "\n");
    if (preg_match('/\<br\/\>\s*\<a href=\"(.*?)\"\>\[link\]\<\/a\>/', $desc, $matches) > 0)
    {
        $m = $matches[1];
        print("  + matched '$m'\n");
    }
else
{
print("  - no match\n");
}
//<a href="http://i.imgur.com/VS4vg.jpg">[link]</a>
} // foreach

?>

