<?php

define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');
define('MAGPIE_CACHE_ON', false);
define('MAGPIE_FETCH_TIME_OUT', 30); // 30 second timeout

require_once 'magpierss/rss_fetch.inc';

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
        //$desc .= "<br/><hr/><center><img src='$url'/></center>";
        $desc .= "<br/><hr/><img src='$url' style='max-width: 100%;'/>";
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

    $rss->channel['title'] = 'inline reddit';
    $rss->image['title'] = 'inline reddit';

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
$feedurl = 'http://reddit.com/.rss';
if (!verify_cache($cachefname, $feedurl, 60))
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

