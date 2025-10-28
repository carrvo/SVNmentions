# SVNmentions

This is an endpoint to compliment [mod_dav_svn](https://svnbook.red-bean.com/en/1.7/svn.ref.mod_dav_svn.conf.html)
by adding a [webmentions](https://www.w3.org/TR/webmention/) receiver that can update an HTML document within SVN.

This is complimented by a [webmentions sender hook](https://github.com/carrvo/SVNmentions-hook).

## Security

Note that SVNmentions obtains direct access to the SVN repository and bypasses any Apache-level Authorization that has been set up. This, and its ability to inject HTML into the repository makes it an attack vector. Consider extending with or applying some amount of spam or other filtering to reduce the risk.

## Setup

Note: requires PHP 8.3+

1. Run `dependencies.bash` to install dependent Ubuntu packages:
    - [Apache HTTPd](https://httpd.apache.org/)
    - [mod_dav_svn](https://svnbook.red-bean.com/en/1.7/svn.ref.mod_dav_svn.conf.html)
    - [PHP](https://www.php.net/)
    - [svn](http://subversion.apache.org/) (TODO: replace commandline with installing bindings [php5-svn](https://www.php.net/manual/en/book.svn.php)/[PECL svn](https://pecl.php.net/package/svn))
1. Configure! (Replace `<>` with real values.)
    ```
    <Location </svn>>
	    DAV svn
	    SVNParentPath </path/to/parent>
    </Location>
    AliasMatch ^/SVNmentions$ /usr/local/src/SVNmentions/SVNmentions.php
    <Directory "usr/local/src/SVNmentions/">
	    SetEnv Context '{"SVNParentPath":"</path/to/parent>","SVNLocationPath":"</svn>"}'
        Require all granted
    </Directory>
    ```

## Usage

### Apache Configuration Must Include
- `SetEnv Context` with a `JSON` string for proper substitutions (and, yes, these values are duplicated in your config)
    - `SVNParentPath` - filesystem path to parent directory of repository - this matches the `SVNParentPath` directive
    - `SVNLocationPath` - webspace path that is parent to the repository - this matches the `Location` directive

### Apache Configuration Optional
- `SetEnv WebmentionUsername <SVNmention>` - the username that will be used during the content update to commit to the repository
- `SetEnv WebmentionsCommitMessage "<SVNmention received>"` - the commit message used during the content update to commit to the repository
- `SetEnv WebmentionsAuthz <svn property>` - the SVN property that you set will act as an allowlist of services that have permission to Webmention the file it is set on - I recommend the value `authz:webmention` (to follow the convention in [svn-auth](https://github.com/carrvo/svn-auth))
    - if the property value has `anonymous`, then all services will have permission
    - the absence of this configuration will act the same as `anonymous`
- `SetEnv WebmentionsClientID </path/to/client>` - for SVNmentions' client ID to be `https://example.com/path/to/client` (See [Client ID Metadata](https://datatracker.ietf.org/doc/html/draft-parecki-oauth-client-id-metadata-document) for more information)
- `SetEnv LocalCommentLimit <int>` - the maximum number of characters accepted

### Supporting Endpoints Must Include
These endpoints *must* be within an SVN repository and *must* reside on the same domain as the webmention endpoint.
```html
<link rel="webmention" href="https://example.com/SVNmentions" />
```

```html
<div id="webmentions">
    <div id="webmention-comments" />
</div>
```

### Supporting Endpoints Minimal Recommended Style

```html
<style>
#webmention-comments iframe {
	display: block;
	width: 80%;
}
</style>
```

### Supporting Endpoints Optional

You can customize the embedded HTML by providing templates through SVN properties.
Note that your template will *always* be wrapped inside a `div` with an `id` attribute.

#### SVN Properties Supported
- `webmention:default` - default template to use when no other [types](https://indieweb.org/posts#Types_of_Posts) are supported (this will show under `<div id="webmention-comments" />`)
- `webmention:local-comment` - template for [local comments](https://indieweb.org/local_comments) (this will show under `<div id="webmention-comments" />`)

You can use the [commands](https://svnbook.red-bean.com/en/1.7/svn.ref.svn.html) `svn propget`, `svn propset`, and `svn propedit` to inspect and edit.
Alternatively you can use client features (such as with [TortoiseSVN](https://tortoisesvn.net/)) to inspect and edit.

#### Template Variables Supported
- `<?source?>` - escaped source URI
- `<?source:unsafe?>` - raw source URI (this may be safe because it was queried so it must be a legitimate URI)
- `<?content?>` - escaped [local comment](https://indieweb.org/local_comments)
- `<?content:unsafe?>` - raw [local comment](https://indieweb.org/local_comments)

### Non-Standard Webmentions

Alternatively you can receive **non-standard** Webmentions. This is useful for mime-types that *cannot* have the destination embedded into their file content (such as image files).

#### Local Comments

[Local comments](https://indieweb.org/local_comments) **do not have a source**.
However, the pattern for non-standard Webmentions can still be be leveraged by including the **additional post fields**:
- `type=local-comment`
- `content=XXXXXXX`

Example HTML for a webpage:
```html
<form action="https://example.com/SVNmentions" method="POST">
    <input type="hidden" name="target" value="http://example.com/webpage.html">
    <input type="hidden" name="type" value="local-comment">
    <label for="comment">Comment</label>
    <br />
    <textarea id="comment" name="content" rows="4" cols="50"></textarea>
    <br />
    <input type="submit" value="Submit">
</form>
```

#### WebDAV

These non-standard Webmentions will be sent with the **additional post fields**:
- `type=webdav`
- `property=XXXXXXX` - this property will be verified with a PROPFIND to ensure it references the target (instead of a GET to verify through the content)

For an example sender see the [webmentions sender hook description](https://github.com/carrvo/SVNmentions-hook?tab=readme-ov-file#non-standard-webmentions).

## Testing

Tested with [webmention-testpinger](https://github.com/voxpelli/node-webmention-testpinger).

## License

Copyright 2024 by carrvo

I have not decided on which license to declare as of yet.

