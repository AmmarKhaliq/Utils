<?php
/**
 * JBZoo Utils
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package   Utils
 * @license   MIT
 * @copyright Copyright (C) JBZoo.com,  All rights reserved.
 * @link      https://github.com/JBZoo/Utils
 */

namespace JBZoo\Utils;

/**
 * Class Url
 * @package JBZoo\Utils
 */
class Url
{
    /**
     * URL constants as defined in the PHP Manual under "Constants usable with http_build_url()".
     * @see http://us2.php.net/manual/en/http.constants.php#http.constants.url
     */
    const URL_REPLACE        = 1;
    const URL_JOIN_PATH      = 2;
    const URL_JOIN_QUERY     = 4;
    const URL_STRIP_USER     = 8;
    const URL_STRIP_PASS     = 16;
    const URL_STRIP_AUTH     = 32;
    const URL_STRIP_PORT     = 64;
    const URL_STRIP_PATH     = 128;
    const URL_STRIP_QUERY    = 256;
    const URL_STRIP_FRAGMENT = 512;
    const URL_STRIP_ALL      = 1024;

    const ARG_SEPARATOR = '&';

    /**
     * Add or remove query arguments to the URL.
     *
     * @param  mixed $newParams Either newkey or an associative array
     * @param  mixed $uri       URI or URL to append the queru/queries to.
     * @return string
     */
    public static function addArg(array $newParams, $uri = null)
    {
        $uri = is_null($uri) ? Vars::get($_SERVER['REQUEST_URI'], '') : $uri;

        // Parse the URI into it's components
        $puri = parse_url($uri);
        if (isset($puri['query'])) {
            parse_str($puri['query'], $queryParams);
            $queryParams = array_merge($queryParams, $newParams);

        } elseif (isset($puri['path']) && strstr($puri['path'], '=') !== false) {
            $puri['query'] = $puri['path'];
            unset($puri['path']);
            parse_str($puri['query'], $queryParams);
            $queryParams = array_merge($queryParams, $newParams);

        } else {
            $queryParams = $newParams;
        }

        // Strip out any query params that are set to false.
        // Properly handle valueless parameters.
        foreach ($queryParams as $param => $value) {
            if ($value === false) {
                unset($queryParams[$param]);

            } elseif ($value === null) {
                $queryParams[$param] = '';
            }
        }

        // Re-construct the query string
        $puri['query'] = self::build($queryParams);

        // Strip = from valueless parameters.
        $puri['query'] = preg_replace('/=(?=&|$)/', '', $puri['query']);

        // Re-construct the entire URL
        $nuri = self::buildAll($puri);

        // Make the URI consistent with our input
        if ($nuri[0] === '/' && strstr($uri, '/') === false) {
            $nuri = substr($nuri, 1);
        }

        if ($nuri[0] === '?' && strstr($uri, '?') === false) {
            $nuri = substr($nuri, 1);
        }

        return rtrim($nuri, '?');
    }

    /**
     * Return the current URL.
     *
     * @return string
     */
    public static function current()
    {
        $url = '';

        // Check to see if it's over https
        $isHttps = self::isHttps();
        if ($isHttps) {
            $url .= 'https://';
        } else {
            $url .= 'http://';
        }

        // Was a username or password passed?
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $url .= $_SERVER['PHP_AUTH_USER'];
            if (isset($_SERVER['PHP_AUTH_PW'])) {
                $url .= ':' . $_SERVER['PHP_AUTH_PW'];
            }
            $url .= '@';
        }

        // We want the user to stay on the same host they are currently on,
        // but beware of security issues
        // see http://shiflett.org/blog/2006/mar/server-name-versus-http-host
        $url .= $_SERVER['HTTP_HOST'];
        $port = $_SERVER['SERVER_PORT'];

        // Is it on a non standard port?
        if ($isHttps && ($port != 443)) {
            $url .= ':' . $_SERVER['SERVER_PORT'];
        } elseif (!$isHttps && ($port != 80)) {
            $url .= ':' . $_SERVER['SERVER_PORT'];
        }

        // Get the rest of the URL
        if (!isset($_SERVER['REQUEST_URI'])) {
            // Microsoft IIS doesn't set REQUEST_URI by default
            $url .= $_SERVER['PHP_SELF'];

            if (isset($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }

        } else {
            $url .= $_SERVER['REQUEST_URI'];
        }

        return $url;
    }

    /**
     * @param array $queryParams
     * @return string
     */
    public static function build(array $queryParams)
    {
        return http_build_query($queryParams, null, self::ARG_SEPARATOR);
    }

    /**
     * Build a URL. The parts of the second URL will be merged into the first according to the flags argument.
     * @author Jake Smith <theman@jakeasmith.com>
     * @see    https://github.com/jakeasmith/http_build_url/
     *
     * @param mixed $url    (part(s) of) an URL in form of a string or associative array like parse_url() returns
     * @param mixed $parts  same as the first argument
     * @param int   $flags  a bitmask of binary or'ed HTTP_URL constants; HTTP_URL_REPLACE is the default
     * @param array $newUrl if set, it will be filled with the parts of the composed url like parse_url() would return
     * @return string
     */
    public static function buildAll($url, $parts = array(), $flags = self::URL_REPLACE, &$newUrl = array())
    {
        is_array($url) || $url = parse_url($url);
        is_array($parts) || $parts = parse_url($parts);

        isset($url['query']) && is_string($url['query']) || $url['query'] = null;
        isset($parts['query']) && is_string($parts['query']) || $parts['query'] = null;
        $keys = array('user', 'pass', 'port', 'path', 'query', 'fragment');

        // HTTP_URL_STRIP_ALL and HTTP_URL_STRIP_AUTH cover several other flags.
        if ($flags & self::URL_STRIP_ALL) {
            $flags |= self::URL_STRIP_USER | self::URL_STRIP_PASS
                | self::URL_STRIP_PORT | self::URL_STRIP_PATH
                | self::URL_STRIP_QUERY | self::URL_STRIP_FRAGMENT;

        } elseif ($flags & self::URL_STRIP_AUTH) {
            $flags |= self::URL_STRIP_USER | self::URL_STRIP_PASS;
        }

        // Schema and host are alwasy replaced
        foreach (array('scheme', 'host') as $part) {
            if (isset($parts[$part])) {
                $url[$part] = $parts[$part];
            }
        }

        if ($flags & self::URL_REPLACE) {
            foreach ($keys as $key) {
                if (isset($parts[$key])) {
                    $url[$key] = $parts[$key];
                }
            }

        } else {
            if (isset($parts['path']) && ($flags & self::URL_JOIN_PATH)) {
                if (isset($url['path']) && substr($parts['path'], 0, 1) !== '/') {
                    $url['path'] = rtrim(str_replace(basename($url['path']), '', $url['path']), '/')
                        . '/' . ltrim($parts['path'], '/');

                } else {
                    $url['path'] = $parts['path'];
                }
            }

            if (isset($parts['query']) && ($flags & self::URL_JOIN_QUERY)) {
                if (isset($url['query'])) {
                    parse_str($url['query'], $urlQuery);
                    parse_str($parts['query'], $partsQuery);

                    $queryParams  = array_replace_recursive($urlQuery, $partsQuery);
                    $url['query'] = self::build($queryParams);

                } else {
                    $url['query'] = $parts['query'];
                }
            }
        }

        if (isset($url['path']) && substr($url['path'], 0, 1) !== '/') {
            $url['path'] = '/' . $url['path'];
        }

        foreach ($keys as $key) {
            $strip = 'URL_STRIP_' . strtoupper($key);
            if ($flags & constant(__CLASS__ . '::' . $strip)) {
                unset($url[$key]);
            }
        }

        $parsedString = '';
        if (isset($url['scheme'])) {
            $parsedString .= $url['scheme'] . '://';
        }

        if (isset($url['user'])) {
            $parsedString .= $url['user'];
            if (isset($url['pass'])) {
                $parsedString .= ':' . $url['pass'];
            }
            $parsedString .= '@';
        }

        if (isset($url['host'])) {
            $parsedString .= $url['host'];
        }

        if (isset($url['port'])) {
            $parsedString .= ':' . $url['port'];
        }

        if (!empty($url['path'])) {
            $parsedString .= $url['path'];
        } else {
            $parsedString .= '/';
        }

        if (isset($url['query'])) {
            $parsedString .= '?' . $url['query'];
        }

        if (isset($url['fragment'])) {
            $parsedString .= '#' . $url['fragment'];
        }

        $newUrl = $url;

        return $parsedString;
    }

    /**
     * Checks to see if the page is being server over SSL or not
     *
     * @return boolean
     */
    public static function isHttps()
    {
        return isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    }

    /**
     * Removes an item or list from the query string.
     *
     * @param  string|array $keys Query key or keys to remove.
     * @param  bool         $uri  When false uses the $_SERVER value
     * @return string
     */
    public static function delArg($keys, $uri = null)
    {
        if (is_array($keys)) {
            return self::addArg(array_combine($keys, array_fill(0, count($keys), false)), $uri);
        }

        return self::addArg(array($keys => false), $uri);
    }

    /**
     * Turns all of the links in a string into HTML links.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param  string $text The string to parse
     * @return string
     */
    public static function parseLink($text)
    {
        $text = preg_replace('/&apos;/', '&#39;', $text); // IE does not handle &apos; entity!

        $sectionHtmlPattern = '%            # Rev:20100913_0900 github.com/jmrware/LinkifyURL
                                            # Section text into HTML <A> tags  and everything else.
             (                              # $1: Everything not HTML <A> tag.
               [^<]+(?:(?!<a\b)<[^<]*)*     # non A tag stuff starting with non-"<".
               | (?:(?!<a\b)<[^<]*)+        # non A tag stuff starting with "<".
             )                              # End $1.
             | (                            # $2: HTML <A...>...</A> tag.
                 <a\b[^>]*>                 # <A...> opening tag.
                 [^<]*(?:(?!</a\b)<[^<]*)*  # A tag contents.
                 </a\s*>                    # </A> closing tag.
             )                              # End $2:
             %ix';

        return preg_replace_callback($sectionHtmlPattern, array(__CLASS__, '_linkifyCallback'), $text);
    }

    /**
     * Callback for the preg_replace in the linkify() method.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param  array $matches Matches from the preg_ function
     * @return string
     */
    protected static function _linkifyCallback($matches)
    {
        if (isset($matches[2])) {
            return $matches[2];
        }

        return self::_linkifyRegex($matches[1]);
    }

    /**
     * Callback for the preg_replace in the linkify() method.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param  array $text Matches from the preg_ function
     * @return string
     */
    protected static function _linkifyRegex($text)
    {
        $urlPattern = '/                                            # Rev:20100913_0900 github.com\/jmrware\/LinkifyURL
                                                                    # Match http & ftp URL that is not already linkified
                                                                    # Alternative 1: URL delimited by (parentheses).
            (\()                                                    # $1 "(" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $2: URL.
            (\))                                                    # $3: ")" end delimiter.
            |                                                       # Alternative 2: URL delimited by [square brackets].
            (\[)                                                    # $4: "[" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $5: URL.
            (\])                                                    # $6: "]" end delimiter.
            |                                                       # Alternative 3: URL delimited by {curly braces}.
            (\{)                                                    # $7: "{" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $8: URL.
            (\})                                                    # $9: "}" end delimiter.
            |                                                       # Alternative 4: URL delimited by <angle brackets>.
            (<|&(?:lt|\#60|\#x3c);)                                 # $10: "<" start delimiter (or HTML entity).
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $11: URL.
            (>|&(?:gt|\#62|\#x3e);)                                 # $12: ">" end delimiter (or HTML entity).
            |                                                       # Alt. 5: URL not delimited by (), [], {} or <>.
            (                                                       # $13: Prefix proving URL not already linked.
            (?: ^                                                   # Can be a beginning of line or string, or
             | [^=\s\'"\]]                                          # a non-"=", non-quote, non-"]", followed by
            ) \s*[\'"]?                                             # optional whitespace and optional quote;
              | [^=\s]\s+                                           # or... a non-equals sign followed by whitespace.
            )                                                       # End $13. Non-prelinkified-proof prefix.
            (\b                                                     # $14: Other non-delimited URL.
            (?:ht|f)tps?:\/\/                                       # Required literal http, https, ftp or ftps prefix.
            [a-z0-9\-._~!$\'()*+,;=:\/?#[\]@%]+                     # All URI chars except "&" (normal*).
            (?:                                                     # Either on a "&" or at the end of URI.
            (?!                                                     # Allow a "&" char only if not start of an...
            &(?:gt|\#0*62|\#x0*3e);                                 # HTML ">" entity, or
            | &(?:amp|apos|quot|\#0*3[49]|\#x0*2[27]);              # a [&\'"] entity if
            [.!&\',:?;]?                                            # followed by optional punctuation then
            (?:[^a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]|$)              # a non-URI char or EOS.
           ) &                                                      # If neg-assertion true, match "&" (special).
            [a-z0-9\-._~!$\'()*+,;=:\/?#[\]@%]*                     # More non-& URI chars (normal*).
           )*                                                       # Unroll-the-loop (special normal*)*.
            [a-z0-9\-_~$()*+=\/#[\]@%]                              # Last char can\'t be [.!&\',;:?]
           )                                                        # End $14. Other non-delimited URL.
            /imx';

        $urlReplace = '$1$4$7$10$13<a href="$2$5$8$11$14">$2$5$8$11$14</a>$3$6$9$12';

        return preg_replace($urlPattern, $urlReplace, $text);
    }
}
