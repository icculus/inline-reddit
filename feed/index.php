<?php

$disable_cache = true;
$staging = $_SERVER['PHP_SELF'] == '/feed/staging.php';
//$staging = false;

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
function process_item($item)
{
    global $staging;

    $debugext = $staging;

    if ($staging) { print("PROCESS ITEM...\n"); print_r($item); }

    $url = $item['link'];

    $morehtml = '';
    $credithtml = '';
    $appendimg = false;

    // Ignore URL arguments (so we know that Amazon AWS is a simple image URL).
    $ext = $url;
    if ($debugext) print("ext start: '$ext'\n");
    $ext = preg_replace('/\?.*/', '', $ext, 1);
    if ($debugext) print("ext minus args: '$ext'\n");
    $ext = preg_replace('/^.*\//', '', $ext, 1);  // drop all paths
    if ($debugext) print("ext minus paths: '$ext'\n");
    $ext = preg_replace('/^.*(\..*)$/', '$1', $ext, 1);  // extract file extension, if any.
    if (isset($ext) && (strlen($ext) > 0) && ($ext[0] != '.')) $ext = '';
    if ($debugext) print("ext minus everything: '$ext'\n");

    if ($debugext) print("url is '$url', ext is '$ext'\n");

    // !!! FIXME: force some of these to https?
    if (($ext == NULL) || (strlen($ext) == 0))  // no filename extension on this URL?
    {
        if (preg_match('/^.*?\:\/\/(.*?\.|)imgur\.com\/(.*)$/', $url, $matches) > 0)
        {
            // pull imgur image out of base URL.
            $appendimg = (strncmp($matches[2], 'a/', 2) != 0);  // don't append albums.
            if (!$appendimg)
                $credithtml = "<br/><font size='-2'><a href='$url'>view this album at imgur.com</a></font>";
            else
            {
                $credithtml = "<br/><font size='-2'><a href='$url'>view this image at imgur.com</a></font>";
                $url .= '.jpg';
            } // else
        } // if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)youtu\.be\/.*$/', $url) > 0)   // stupid youtube short url.
        {
            // pull youtube video out of base URL.
            $embedurl = preg_replace('/^(.*)\/youtu\.be\/(.*)$/', '$1/www.youtube.com/embed/$2', $url, 1);
            $url = preg_replace('/^(.*)\/youtu\.be\/(.*)$/', '$1/www.youtube.com/watch?v=$2', $url, 1);
            $morehtml = "<br/><hr/><iframe width='560' height='315' src='$embedurl' frameborder='0' allowfullscreen></iframe>";
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at youtube.com</a></font>";
        } // else if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)youtube\.com\/.*$/', $url) > 0)
        {
            // pull youtube video out of base URL.
            $embedurl = preg_replace('/^(.*)\/.*?youtube.com\/(.*)$/', '$1/www.youtube.com/embed/$2', $url, 1);
            $url = preg_replace('/^(.*)\/.*?youtube.com\/(.*)$/', '$1/www.youtube.com/watch?v=$2', $url, 1);
            $morehtml = "<br/><hr/><iframe width='560' height='315' src='$embedurl' frameborder='0' allowfullscreen></iframe>";
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at youtube.com</a></font>";
        } // if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)(quickmeme\.com\/meme|qkme\.me)\/(\?id=\d+$|[0-9-A-Za-z]+)\/?/', $url, $matches) > 0)
        {
            // pull quickmeme image out of base URL.
            $appendimg = true;
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at quickmeme.com</a></font>";
            $imgid = $matches[3];
            $url = "http://i.qkme.me/$imgid.jpg";
        } // else if
        else if (preg_match('/^.*?\:\/\/(.*?\.|)livememe\.com\/(.*?)$/', $url, $matches) > 0)
        {
            // pull livememe image out of base URL.
            $appendimg = true;
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at livememe.com</a></font>";
            $imgid = $matches[2];
            $url = "http://i.lvme.me/$imgid.jpg";
        } // else if
        else if (preg_match('/^.*?\:\/\/gfycat\.com\/(.*?)$/', $url, $matches) > 0)
        {
            // pull gfycat image out of base URL.
            $appendimg = true;
            $credithtml = "<br/><font size='-2'><a href='$url'>view this at gfycat.com</a></font>";
            $imgid = $matches[1];
            $url = "https://thumbs.gfycat.com/$imgid-size_restricted.gif";
        } // else if
    } // if

    // URL filename has an extension?
    else if (strcasecmp($ext, '.jpg') == 0)
        $appendimg = true;
    else if (strcasecmp($ext, '.jpeg') == 0)
        $appendimg = true;
    else if (strcasecmp($ext, '.png') == 0)
        $appendimg = true;
    else if (strcasecmp($ext, '.gif') == 0)
        $appendimg = true;
    else if (strcasecmp($ext, '.gifv') == 0)  // imgur "video"?
    {
        $embedurl = preg_replace('/^(.*?\:\/\/)(.*?\.|)(imgur\.com\/)(.*)\.gifv$/', '$1$2$3$4.gif', $url, 1);
        if ($embedurl != $url)
        {
            $credithtml = "<br/><font size='-2'><a href='$url'>view this video at imgur.com</a></font>";
            $appendimg = true;
            $url = $embedurl;
        } // if
    } // else if

    if (!$appendimg && empty($morehtml) && !empty($item['thumbnail']))  // oh well, use reddit's thumbnail if there is one.
    {
        if ($staging) print("USE THE THUMBNAIL\n");
        if (strncasecmp($item['thumbnail'], 'http', 4) == 0)   // probably a URL and not 'nsfw' or 'self', etc
        {
            $appendimg = true;
            $url = $item['thumbnail'];
        } // if
    } // else if

    // !!! FIXME: Handle yfrog, etc.

    $desc = $item['summary'];
    $appenddesc = '';
    if ($appendimg)
    {
        $appenddesc = "<br/><hr/><img src='$url' style='max-width: 100%;'/>";
        $desc .= $appenddesc;
    }

    if ($staging)
    {
        print("DESC APPEND: '$appenddesc'\n");
        print("DESC MORE HTML: '$morehtml'\n");
        print("DESC CREDIT HTML: '$credithtml'\n");
    } // if

    $desc .= $morehtml;
    $desc .= $credithtml;

    return $desc;
} // process_item


function recache($subreddit, $fname, $url)
{
    global $staging;

    if ($staging) print("RECACHE: subreddit='$subreddit' fname='$fname' url='$url'\n");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    $jsondata = curl_exec($ch);
    curl_close($ch);
    if ($jsondata === false)
        return false;

    $json = json_decode($jsondata);
    if (($json == NULL) || ($json->kind != 'Listing'))
        return false;

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
    if ($staging)
        $title = "[STAGING] $title";

    $channel = array(
        'title' => $title,
        'link' => $oururl,
        'tagline' => '',  // !!! FIXME: ?
    );

    $image = array(   // !!! FIXME
        'url' => 'http://static.reddit.com/reddit.com.header.png',
        'title' => $title,
        'link' => $oururl,
    );

    $linktothread = ((isset($_REQUEST['linktothread'])) && (intval($_REQUEST['linktothread']) != 0));

    $items = array();
    foreach ($json->data->children as $obj)
    {
        $item = $obj->data;

        $nsfw = $item->over_18 ? "<font color='#FF0000'>[NSFW]</font>" : '';

        $maybeslash = (substr($item->permalink, 0, 1) == '/') ? '' : '/';
        $permalink = "https://www.reddit.com{$maybeslash}{$item->permalink}";
        $itemurl = $item->url;
        $titleurl = $linktothread ? $permalink : $itemurl;

        $desc = <<<EOF
<a href='$titleurl'>{$item->title}</a> (<a href='https://www.reddit.com/domain/{$item->domain}/'>{$item->domain}</a>)<br/>
submitted by <a href='https://www.reddit.com/user/{$item->author}'>{$item->author}</a> to <a href='https://www.reddit.com/r/{$item->subreddit}'>/r/{$item->subreddit}</a><br/>
$nsfw <a href='$permalink'>{$item->num_comments} comments</a> <a href='$itemurl'>original</a>
EOF;

        $pubdate = $item->created_utc;
        $dt = new DateTime("@$pubdate");
        $pubdatefmt = $dt->format(DateTime::RSS);

        $items[] = array(
            'guid' => $permalink,  // this matches the guid we used when scraping the RSS feeds.
            'title' => $item->title,
            'link' => $titleurl,
            'summary' => $desc,
            'description' => $desc,
            'pubdate' => $pubdatefmt,
            'thumbnail' => $item->thumbnail
        );
    } // foreach

    doWriteXml($ok, $io, $channel);
    doWrite($ok, $io, '<image>');
    doWriteXml($ok, $io, $image);
    doWrite($ok, $io, '</image>');

    foreach ($items as $item)
    {
        if (!$ok)
            break;

        $desc = process_item($item);
        $item['summary'] = $desc;
        $item['description'] = $desc;

        unset($item['thumbnail']);  // don't put this in the final XML output.

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
    global $disable_cache, $staging;
    $rc = (($maxage < 0) ? false : @filemtime($fname));
    $retval = true;
    if ( $disable_cache || $staging || ($rc === false) || (($rc + $maxage) < time()) )
        $retval = recache($subreddit, $fname, $url);
    return $retval;
} // verify_cache



// Mainline!


// We only want to talk to Reddit's servers once every X seconds at most, as
//  each individual RSS download ends up taking several seconds, so we'll live
//  with slightly outdated results to make this site more responsive.
$use_google = false;  // get this from Google Reader's cache by default.
$cachefname = 'processed-rss.xml';
$subreddit = 'front page';
$feedurl = 'http://reddit.com/';

if ($staging)
{
    header('Content-Type: text/plain; charset=UTF-8');
    print("\n\n\nSTAGING!\n\n\n");
} // else

if (isset($_REQUEST['subreddit']))
{
    $use_google = false;   // these tend to be broken on Google Reader.  :/
    $str = $_REQUEST['subreddit'];
    if ((strlen($str) < 32) && (preg_match('/^[\\a-zA-Z0-9\-_]+$/', $str) == 1))
    {
        $cachefname = "$str-$cachefname";
        $feedurl .= "r/$str/";
        $subreddit = $str;
    } // if
} // if
else if (isset($_REQUEST['multireddit']))
{
    $use_google = false;   // these tend to be broken on Google Reader.  :/

    if (!isset($_REQUEST['user']))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nRequested multireddit without username.\n\n");
        exit(0);
    } // if

    $user = $_REQUEST['user'];
    if ((strlen($user) > 64) || (preg_match('/^[a-zA-Z0-9\-_]+$/', $user) != 1))
    {
        header('HTTP/1.0 400 Bad Request');
        header('Connection: close');
        header('Content-Type: text/plain');
        print("\n\nBogus multireddit username.\n\n");
        exit(0);
    } // if

    $str = $_REQUEST['multireddit'];
    if ((strlen($str) < 32) && (preg_match('/^[a-zA-Z0-9\-_]+$/', $str) == 1))
    {
        $cachefname = "multireddit-user-$user-$str-$cachefname";
        $cachefname = "$str-$cachefname";
        $name = "user/$user/m/$str";
        $feedurl .= $name;
        $subreddit = "Multireddit $name";
    } // if
} // if

$feedurl .= '.json';

// Private feeds look like this:
//  http://www.reddit.com/.json?feed=<some_sha1_looking_hash>&user=<your_reddit_login_name>
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

if ($staging)
    $cachefname = "$cachefname-staging";

// make sure "subreddit/sorting" doesn't look like a subdir.
$cachefname = strtr($cachefname, '/', '_');

if (!verify_cache($cachefname, $feedurl, $subreddit, 60))
{
    header('HTTP/1.0 503 Service unavailable');
    header('Connection: close');
    header('Content-Type: text/plain');
    print("\n\nCouldn't get the reddit RSS feed. Try again later.\n\n");
    exit(0);
} // if

if ($staging)
    print("\n\nXML output follows...\n\n\n");
else
    header('Content-Type: text/xml; charset=UTF-8');

@readfile($cachefname);  // dump the XML we generated to the client and gtfo.

if ($staging)
    unlink($cachefname);  // staging!

?>
