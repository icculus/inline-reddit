<?php

define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

require_once 'magpierss/rss_fetch.inc';

function doWrite(&$ok, $io, $str)
{
    return $ok && (@fwrite($io, $str) === strlen($str));
} // doWrite

function doWriteXml(&$ok, $io, $arr)
{
    if ($ok)
    {
        foreach ($arr as $k => $v)
        {
            if (!is_array($v))
                doWrite($ok, $io, "<$k>$v</$k>");
            else
            {
                doWrite($ok, $io, "<$k>");
                doWriteXml($ok, $io, $v);
                doWrite($ok, $io, "</$k>");
            } // else
        } // foreach
    } // if

    return $ok;
} // doWriteXml


// This is where most of the magic happens.
function process_item($item, $url)
{
    $credithtml = '';
    $appendimg = false;
    $ext = strrchr($url, '/');      // skip past protocol, hosts, paths, etc...
    if ($ext !== false)
        $ext = strrchr($ext, '.');  //  ... and get the filename extension.

    if ($ext === false)  // no filename extension on this URL?
    {
        $imgurbase = 'http://imgur.com/';
        $imgurbaselen = strlen($imgurbase);

        // pull imgur image out of base URL.
        if (strncasecmp($url, $imgurbase, $imgurbaselen) == 0)
        {
            $credithtml = "<br/><center><font size='-2'><a href='$url'>view this at imgur.com</a></font></center>";
            $url .= '.jpg';
        } // if
    } // if

    else  // URL filename has an extension.
    {
        if (strcasecmp($ext, '.jpg') == 0)
            $appendimg = true;
        else if (strcasecmp($ext, '.png') == 0)
            $appendimg = true;
        else if (strcasecmp($ext, '.gif') == 0)
            $appendimg = true;
    } // else

    // !!! FIXME: Handle YouTube, etc.

    $desc = $item['description'];
    if ($appendimg)
        $desc .= "<br/><hr/><center><img src='$url'/></center>";
        //$desc .= "<br/><hr/><center><img src='$url' style='max-width: 100%;'/></center>";
    $desc .= $credithtml;

    return $desc;
} // process_item


function recache($fname, $url)
{
    $rss = fetch_rss($url);
    if ($rss === false)
        return false;

    // !!! FIXME: This is all pretty ghetto.
    $tmpfname = "tmp-" . getmypid();
    $io = @fopen($tmpfname, "w");
    $ok = ($io !== false);

    doWrite($ok, $io,
           '<?xml version="1.0" encoding="UTF-8"?>' .
           '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"' .
           ' xmlns:media="http://search.yahoo.com/mrss/"><channel>');

    doWriteXml($ok, $io, $rss->channel);
    doWrite($ok, $io, '<image>');
    doWriteXml($ok, $io, $rss->image);
    doWrite($ok, $io, '</image>');

    $pattern = '/\<br\/\>\s*\<a href=\"(.*?)\"\>\[link\]\<\/a\>/';
    foreach ($rss->items as $item)
    {
        $desc = $item['description'];
        if (!$ok)
            break;
        if (preg_match($pattern, $desc, $matches) > 0)
            $desc = process_item($item, $matches[1]);
        unset($matches);

        $item['summary'] = $desc;
        $item['description'] = $desc;

        doWrite($ok, $io, '<item>');
        doWriteXml($ok, $io, $item);
        doWrite($ok, $io, '</item>');
    } // foreach

    doWrite($ok, $io, '</channel></rss>');

    if (($io !== false) && (!@fclose($io)))
        $ok = false;

    if ($ok)
        $ok = @rename($tmpfname, $fname);
    else
        @unlink($tmpfname);

    return $ok;
} // recache


function verify_cache($fname, $url, $maxage)
{
    $rc = @filemtime($fname);
    $retval = true;
    if ( ($rc === false) || (($rc + $maxage) < time()) )
        $retval = recache($fname, $url);
    return $retval;
} // verify_cache



// Mainline!


// We only want to talk to Reddit's servers once every X seconds at most, as
//  each individual RSS download ends up taking several seconds, so we'll live
//  with slightly outdated results to make this site more responsive.
$cachefname = 'processed-rss.xml';
if (!verify_cache($cachefname, 'http://www.reddit.com/.rss', 60))
{
    header('HTTP/1.0 503 Service unavailable');
    header('Connection: close');
    header('Content-Type: text/plain');
    print("\n\nCouldn't get the reddit RSS feed. Try again later.\n\n");
    exit(0);
} // if

header('Content-Type: text/xml; charset=UTF-8');
@readfile($cachefname);  // dump the XML we generated to the client and gtfo.

?>

