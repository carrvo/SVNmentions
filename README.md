# SVNmentions

This is an endpoint to compliment [mod_dav_svn](https://svnbook.red-bean.com/en/1.7/svn.ref.mod_dav_svn.conf.html)
by adding a [webmentions](https://www.w3.org/TR/webmention/) receiver that can update an HTML document within SVN.

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

## Testing

Tested with [webmention-testpinger](https://github.com/voxpelli/node-webmention-testpinger).

## License

Copyright 2024 by carrvo

I have not decided on which license to declare as of yet.

