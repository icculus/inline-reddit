<?php

set_include_path('../../pear/php' . PATH_SEPARATOR . './XML_Feed_Parser' . PATH_SEPARATOR . get_include_path());
require_once 'XML/Feed/Parser.php';

// !!! FIXME: there has got to be a better way to do this. Maybe move to
// !!! FIXME:  the formal XML writer classes.
$xmlents = array('&#34;','&#38;','&#38;','&#60;','&#62;','&#160;','&#161;','&#162;','&#163;','&#164;','&#165;','&#166;','&#167;','&#168;','&#169;','&#170;','&#171;','&#172;','&#173;','&#174;','&#175;','&#176;','&#177;','&#178;','&#179;','&#180;','&#181;','&#182;','&#183;','&#184;','&#185;','&#186;','&#187;','&#188;','&#189;','&#190;','&#191;','&#192;','&#193;','&#194;','&#195;','&#196;','&#197;','&#198;','&#199;','&#200;','&#201;','&#202;','&#203;','&#204;','&#205;','&#206;','&#207;','&#208;','&#209;','&#210;','&#211;','&#212;','&#213;','&#214;','&#215;','&#216;','&#217;','&#218;','&#219;','&#220;','&#221;','&#222;','&#223;','&#224;','&#225;','&#226;','&#227;','&#228;','&#229;','&#230;','&#231;','&#232;','&#233;','&#234;','&#235;','&#236;','&#237;','&#238;','&#239;','&#240;','&#241;','&#242;','&#243;','&#244;','&#245;','&#246;','&#247;','&#248;','&#249;','&#250;','&#251;','&#252;','&#253;','&#254;','&#255;');
$htmlents = array('&quot;','&amp;','&amp;','&lt;','&gt;','&nbsp;','&iexcl;','&cent;','&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;','&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;','&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;','&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;','&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;','&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;','&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;','&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;','&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;','&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;','&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;','&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;','&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;','&yacute;','&thorn;','&yuml;');
function xml_entities($str) 
{ 
    global $xmlents, $htmlents;
    $str = htmlspecialchars($str, ENT_NOQUOTES, 'UTF-8');
    $str = str_replace($htmlents, $xmlents, $str); 
    $str = str_ireplace($htmlents, $xmlents, $str); 
    return $str; 
} // xml_entities

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
                doWrite($ok, $io, "<$k>" . xml_entities($v) . "</$k>");
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
    $morehtml = '';
    $credithtml = '';
    $appendimg = false;
    $ext = strrchr($url, '/');      // skip past protocol, hosts, paths, etc...
    if ($ext !== false)
        $ext = strrchr($ext, '.');  //  ... and get the filename extension.

    if ($ext === false)  // no filename extension on this URL?
    {
        if (preg_match('/^.*?\:\/\/(.*?\.|)imgur\.com\/.*$/', $url) > 0)
        {
            // pull imgur image out of base URL.
            $appendimg = true;
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at imgur.com</a></font>";
            $url .= '.jpg';
        } // if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)youtube\.com\/.*$/', $url) > 0)
        {
            // pull youtube video out of base URL.
            $url = preg_replace('/\/watch\?v\=/', '/v/', $url, 1);
            $morehtml = "<br/><hr/><object width='480' height='385'>" .
                        "<param name='movie' value='$url'></param>" .
                        "<param name='allowscriptaccess' value='always'></param>" .
                        "<embed src='$url' type='application/x-shockwave-flash'" .
                        " allowscriptaccess='always' width='480' height='385'>" .
                        "</embed></object>";

            $credithtml = "<br/><font size='-2'><a href='$url'>view this at youtube.com</a></font>";
        } // if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)(quickmeme\.com\/meme|qkme\.me)\/(.*?)(\/|\?id=\d+$)/', $url, $matches) > 0)
        {
            // pull quickmeme image out of base URL.
            $appendimg = true;
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at quickmeme.com</a></font>";
            $imgid = $matches[3];
            $url = "http://i.qkme.me/$imgid.jpg";
        } // else if
    } // if

    else  // URL filename has an extension.
    {
        if (strcasecmp($ext, '.jpg') == 0)
            $appendimg = true;
        else if (strcasecmp($ext, '.jpeg') == 0)
            $appendimg = true;
        else if (strcasecmp($ext, '.png') == 0)
            $appendimg = true;
        else if (strcasecmp($ext, '.gif') == 0)
            $appendimg = true;
    } // else

    // !!! FIXME: Handle yfrog, etc.

    $desc = $item['summary'];
    if ($appendimg)
        $desc .= "<br/><hr/><img src='$url' style='max-width: 100%;'/>";
    $desc .= $morehtml;
    $desc .= $credithtml;

    return $desc;
} // process_item


function recache($subreddit, $fname, $url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    $xmldata = curl_exec($ch);
    curl_close($ch);
    if ($xmldata === false)
        return false;

    try {
        $feed = new XML_Feed_Parser($xmldata, false, true, true);
    } catch (XML_Feed_Parser_Exception $e) {
        //die('Feed invalid: ' . $e->getMessage());
        return false;
    }

    // !!! FIXME: This is all pretty ghetto.
    $tmpfname = "tmp-" . getmypid();
    $io = @fopen($tmpfname, "w");
    $ok = ($io !== false);

    doWrite($ok, $io,
           '<?xml version="1.0" encoding="UTF-8"?>' .
           '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"' .
           ' xmlns:media="http://search.yahoo.com/mrss/"><channel>');

    $oururl = $_SERVER['PHP_SELF'];
    $title = "inline reddit: $subreddit";

    $channel = array(
        title => $title,
        link => $oururl,
        tagline => '',  // !!! FIXME: ?
    );

    $image = array(   // !!! FIXME
        url => 'http://static.reddit.com/reddit.com.header.png',
        title => $title,
        link => $oururl,
    );

    $items = array();
    foreach ($feed as $item)
    {
        $items[] = array(
            guid => $item->id,
            title => $item->title,
            link => $item->link,
            summary => $item->summary,
            description => $item->summary,
            pubdate => $item->pubDate,
        );
    } // foreach

    doWriteXml($ok, $io, $channel);
    doWrite($ok, $io, '<image>');
    doWriteXml($ok, $io, $image);
    doWrite($ok, $io, '</image>');

    $pattern = '/\<br\>\s*\<a href=\"(.*?)\"\>\[link\]\<\/a\>/';
    foreach ($items as $item)
    {
        if (!$ok)
            break;
        $desc = $item['summary'];
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


function verify_cache($fname, $url, $subreddit, $maxage)
{
    $rc = (($maxage < 0) ? false : @filemtime($fname));
    $retval = true;
    if ( ($rc === false) || (($rc + $maxage) < time()) )
        $retval = recache($subreddit, $fname, $url);
    return $retval;
} // verify_cache



// Mainline!


// We only want to talk to Reddit's servers once every X seconds at most, as
//  each individual RSS download ends up taking several seconds, so we'll live
//  with slightly outdated results to make this site more responsive.
$use_google = true;  // get this from Google Reader's cache by default.
$cachefname = 'processed-rss.xml';
$subreddit = 'front page';
$feedurl = 'http://reddit.com/';

if (isset($_REQUEST['subreddit']))
{
    $str = $_REQUEST['subreddit'];
    if ((strlen($str) < 32) && (preg_match('/^[a-zA-Z0-9]+$/', $str) == 1))
    {
        $cachefname = "$str-$cachefname";
        $feedurl .= "r/$str/";
        $subreddit = $str;
    } // if
} // if

$feedurl .= '.rss';

// Private feeds look like this:
//  http://www.reddit.com/.rss?feed=<some_sha1_looking_hash>=&user=<your_reddit_login_name>
// This exposes your private feeds to the world! Use these at your own risk!
if (isset($_REQUEST['feed']))
{
    if (isset($_REQUEST['subreddit']))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nCan't specify a subreddit AND a feed.\n\n");
        exit(0);
    } // if

    if (!isset($_REQUEST['user']))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nRequested feed without username.\n\n");
        exit(0);
    } // if

    $feed = $_REQUEST['feed'];
    if ((strlen($feed) != 40) || (preg_match('/^[a-zA-Z0-9]+$/', $feed) != 1))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nBogus feed hash.\n\n");
        exit(0);
    } // if

    $user = $_REQUEST['user'];
    if ((strlen($user) > 64) || (preg_match('/^[a-zA-Z0-9\-_]+$/', $user) != 1))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nBogus feed username.\n\n");
        exit(0);
    } // if

    $cachefname = "feed-$feed-user-$user-$cachefname";
    $feedurl .= "?feed=$feed&user=$user";
    $subreddit = "private feed $feed for user $user";
    $use_google = false;
} // if

// Use Google Reader's republication of reddit's stream, since they can spare
//  the resources.  :)   Also, they tend to pick up items that pop into
//  reddit's RSS feed for a brief time, so you get more content in general.
if ($use_google)
    $feedurl = "http://www.google.com/reader/public/atom/feed/$feedurl";

if (!verify_cache($cachefname, $feedurl, $subreddit, 60))
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
