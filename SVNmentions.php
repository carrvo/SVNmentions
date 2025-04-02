<?php
// Temporary files

/**
 * Credit to https://stackoverflow.com/a/30010928
 *
 * Creates a random unique temporary directory, with specified parameters,
 * that does not already exist (like tempnam(), but for dirs).
 *
 * Created dir will begin with the specified prefix, followed by random
 * numbers.
 *
 * @link https://php.net/manual/en/function.tempnam.php
 *
 * @param string|null $dir Base directory under which to create temp dir.
 *     If null, the default system temp dir (sys_get_temp_dir()) will be
 *     used.
 * @param string $prefix String with which to prefix created dirs.
 * @param int $mode Octal file permission mask for the newly-created dir.
 *     Should begin with a 0.
 * @param int $maxAttempts Maximum attempts before giving up (to prevent
 *     endless loops).
 * @return string|bool Full path to newly-created dir, or false on failure.
 */
function tempdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 1000)
{
    /* Use the system temp dir by default. */
    if (is_null($dir))
    {
        $dir = sys_get_temp_dir();
    }

    /* Trim trailing slashes from $dir. */
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);

    /* If we don't have permission to create a directory, fail, otherwise we will
     * be stuck in an endless loop.
     */
    if (!is_dir($dir) || !is_writable($dir))
    {
        return false;
    }

    /* Make sure characters in prefix are safe. */
    if (strpbrk($prefix, '\\/:*?"<>|') !== false)
    {
        return false;
    }

    /* Attempt to create a random directory until it works. Abort if we reach
     * $maxAttempts. Something screwy could be happening with the filesystem
     * and our loop could otherwise become endless.
     */
    $attempts = 0;
    do
    {
        $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
    } while (
        !mkdir($path, $mode) &&
        $attempts++ < $maxAttempts
    );

    return $path;
}

/**
 * Credit to https://stackoverflow.com/a/11614201
 */
function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir") 
           rrmdir($dir."/".$object); 
        else unlink   ($dir."/".$object);
      }
    }
    reset($objects);
    rmdir($dir);
  }
}

// SVN
// see https://www.php.net/manual/en/book.svn.php
// see http://subversion.apache.org/
// see https://svnbook.red-bean.com/en/1.7/index.html

if (!function_exists('svn_checkout')) {
    function  svn_checkout(string $repos, string $targetpath, int $revision = null, int $flags = 0): bool
    {
        $cmd = "svn checkout --depth empty '$repos' '$targetpath'";
        $output = null;
        $retval = null;
        $cmd_ran = exec($cmd, $output, $retval);
        if ($cmd_ran === false) {
            receiverError('', 'SVN checkout failed to run');
        }
        if ($retval !== 0) {
            receiverError('', "SVN checkout returned with status: $retval");
        }
        return true;
    }
}

if (!function_exists('svn_update')) {
    function svn_update(string $path, int $revno = -1, bool $recurse = true): int
    {
        $cmd = "svn update '$path'";
        $output = null;
        $retval = null;
        $cmd_ran = exec($cmd, $output, $retval);
        if ($cmd_ran === false) {
            receiverError('', 'SVN update failed to run');
        }
        if ($retval !== 0) {
            receiverError('', "SVN update returned with status: $retval");
        }
        return -1;
    }
}

if (!function_exists('svn_commit')) {
    function svn_commit(string $log, array $targets, bool $recursive = true): array
    {
        $commit_list = tempnam(null, 'svn-targets_');
        if ($commit_list === false) {
            receiverError('', 'Failed to create temporary file');
        }
        $commit_handle = fopen($commit_list);
        foreach ($targets as $target) {
            fwrite($commit_handle, $target);
        }
        fclose($commit_handle);

        $cmd = "svn commit --user 'SVNmention' -m '$log' --targets '$commit_list'";
        $output = null;
        $retval = null;
        $cmd_ran = exec($cmd, $output, $retval);
        unlink($commit_list);
        if ($cmd_ran === false) {
            receiverError('', 'SVN commit failed to run');
        }
        if ($retval !== 0) {
            receiverError('', "SVN commit returned with status: $retval");
        }
        return [
            -1,
            '',
            'SVNmention',
        ];
    }
}

if (!function_exists('svn_auth_set_parameter')) {
    function svn_auth_set_parameter(string $key, string $value): void
    {
    }
}

function convertToSVNPath(string $location_path): array
{
    global $issuer, $SVNParentPath, $SVNLocationPath;
    if (str_starts_with($location_path, $issuer) !== true) {
        senderError("Target domain is not acceptable, must be in: $issuer");
    }
    $location_path = substr($location_path, strlen($issuer));
    $svn_path = preg_replace('/'.preg_quote($SVNLocationPath, '/').'/', $SVNParentPath, $location_path);
    $svn_path = preg_replace('/#.*$/', '', $svn_path); // remove fragment identifier
    $parent_pos = strrpos($svn_path, '/', 1); // exclude final slash (/) if child is folder
    if ($parent_pos === false) {
        senderError('Target path is not acceptable');
    }
    $svn_name = substr($svn_path, $parent_pos);
    $svn_path = substr($svn_path, 0, $parent_pos);
    return [
        'parent' => $svn_path,
        'name' => $svn_name,
    ];
}

function checkoutContent(array $svn_path, string $temp_path): string
{
    $parent = $svn_path['parent'];
    $working_copy = $temp_path.'/'.$svn_path['name'];
    if (svn_checkout("file://$parent", $temp_path) !== true) {
        receiverError('', "Failed to checkout $parent");
    }
    if (svn_update($working_copy) === false) {
        receiverError('', "Failed to update to $working_copy");
    }
    return $working_copy;
}

function commitContent(string $modified_path): void
{
    svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, 'SVNmention');
    $result = svn_commit('SVNmention received', array($modified_path));
    if ($result === false) {
        receiverError('Failed to add webmention', "Failed to add webmention: $modified_path");
    }
}

// HTML
// see https://www.php.net/manual/en/class.dom-htmldocument.php
// see https://github.com/microformats/php-mf2
// see https://github.com/Masterminds/html5-php
// see https://github.com/barnabywalters/php-mf-cleaner

function initCurl(string $url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 8);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, round(4 * 1000));
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2);
    return $curl;
}

function insertEmbed(Dom\DOMElement $parent, array $embed): void
{
    $el = $parent->ownerDocument->createElement($embed['tagname']);
    foreach ($embed['attributes'] as $name => $value) {
        $el->setAttribute($name, $value);
    }
    $el->textContent = $embed['innerHTML'];
    $parent->append($el);
}

function getEmbed(string $sourceURI, string $targetURI): array
{
    $curl = initCurl($sourceURI);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: text/html']);
    $body = curl_exec($curl);
    curl_close($curl);
    //$dom = Dom\HTMLDocument::createFromString($body);
    //if ($dom->body === null) {
    //    senderError("Could not parse HTML from: $sourceURI");
    //}
    if (preg_match('/('.preg_quote($targetURI, '/').')/', preg_quote($body, '/')) !== 1) {
        //return "<iframe src=\"$sourceURI\"></iframe>";
        return [
            'tagname' => 'iframe',
            'attributes' => [
                'src' => $sourceURI, // should be safe since this was queried so it must be a legitimate URI
            ],
            'innerHTML' => '',
        ];
    }
    senderError("Source `$sourceURI` did not mention target `$targetURI`");
}

/*
function getEmbed(string $sourceURI, string $targetURI): string
{
    $mf = Mf2\fetch($sourceURI);
    if ($mf === null) {
        senderError("Could not parse HTML from: $sourceURI");
    }
    /*
    foreach ($mf['rel-urls'] as $relurl => $data) {
        if (strcmp($relurl, $targetURI) === 0) {
            return "<iframe src=\"$sourceURI\"></iframe>";
        }
    }
    * /
    foreach ($mf['items'] as $mfTopItem) {
        $mfFlat = BarnabyWalters\Mf2\flattenMicroformats($mfTopItem);
        foreach ($mfFlat as $mfSubItem) {
            if (in_array('h-entry', $mfitem['type']) &&
                    BarnabyWalters\Mf2\hasProp($mfSubItem, 'u-in-reply-to') &&
                    strcmp(BarnabyWalters\Mf2\getPlaintext($mfSubItem, 'u-in-reply-to'), $sourceURI) === 0) {
                return "<iframe src=\"$sourceURI\"></iframe>";
            }
        }
    }
    senderError("Source `$sourceURI` did not mention target `$targetURI`");
}
*/

function updateContent(string $filesystem_path, array $comment_embed): void
{
    //$options = array();
    //$htmlparser = new Masterminds\HTML5($options);
    //$dom = $htmlparser->loadHTMLFile($filesystem_path);
    $dom = Dom\HTMLDocument::createFromFile($filesystem_path);
    $webmention_section = $dom->getElementById('webmentions');
    if ($webmention_section === null) {
        receiverError('Target document is missing tag for webmentions!');
    }
    $comment_section = $dom->getElementById('webmention-comments');
    if ($comment_section === null) {
        receiverError('Target document is missing tag for webmention comments!');
    }
    if ($webmention_section->contains($comment_section) !== true) {
        receiverError('Target document does not have comments under webmentions!');
    }
    $new_comment = true;
    foreach ($comment_section as $comment) {
        if (strcmp($comment->getAttribute('src'), $comment_embed['attributes']['src']) === 0) {
            $new_comment = false;
            break;
        }
    }
    if ($new_comment) {
        insertEmbed($comment_section, $comment_embed);
    }
    //$htmlparser->save($dom, $filesystem_path);
    $dom->saveHtmlFile($filesystem_path);
}

// Webmentions
// see https://www.w3.org/TR/webmention/#receiving-webmentions

function receiverError(string $error_message, ?string $log_message = null): void
{
    if ($log_message === null) {
        $log_message = $error_message;
    }
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain;charset=UTF-8');
    if (empty($log_message) === false) {
        error_log('[SVNmentions:info] '.$log_message);
    }
    exit($error_message);
}

function senderError(string $error_message, ?string $log_message = null): void
{
    if ($log_message === null) {
        $log_message = $error_message;
    }
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain;charset=UTF-8');
    if (empty($log_message) === false) {
        error_log('[SVNmentions:info] '.$log_message);
    }
    exit($error_message);
}

function synchronousSuccess(): void
{
    header('HTTP/1.1 200 OK');
    header('Content-Type: text/plain;charset=UTF-8');
    exit();
}

function receiveWebMention(string $sourceURI, string $targetURI)
{
    try {
        if (strcmp($sourceURI, $targetURI) === 0) {
            senderError('Source and target are the same!', '');
        }
        $source_embed = getEmbed($sourceURI, $targetURI); // also verifies source
        $svn_path = convertToSVNPath($targetURI); // also verifies target
        $temp_path = tempdir(null, 'svnmentions_', 0700, 10);
        if ($temp_path === false) {
            receiverError('', 'Failed to create temporary directory.');
        }
        $checkout_path = checkoutContent($svn_path, $temp_path);
        updateContent($checkout_path, $source_embed);
        commitContent($checkout_path);
    } catch (Exception $ex) {
        receiverError('', 'Exception was thrown: '.$ex->getMessage());
    } finally {
        if (isset($temp_path)) {
            rrmdir($temp_path);
        }
    }
}

// parsing request

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    senderError('Must send as POST!', '');
}
if (isset($_POST['source']) === false) {
    senderError('Missing source field!', '');
}
$source = $_POST['source'];
if (isset($_POST['target']) === false) {
    senderError('Missing target field!', '');
}
$target = $_POST['target'];

$issuer = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
$context = json_decode(getenv('CONTEXT'), true);
$SVNParentPath = $context['SVNParentPath'];
if (isset($SVNParentPath) === false) {
    receiverError('Misconfigured endpoint!', 'Misconfigured endpoint: missing SVNParentPath');
}
$SVNLocationPath = $context['SVNLocationPath'];
if (isset($SVNLocationPath) === false) {
    receiverError('Misconfigured endpoint!', 'Misconfigured endpoint: missing SVNLocationPath');
}

receiveWebMention($source, $target);

?>
