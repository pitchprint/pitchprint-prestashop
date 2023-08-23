<?php
/**
* 2023 PitchPrint
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PitchPrint to newer
* versions in the future. If you wish to customize PitchPrint for your
* needs please refer to http://pitchprint.com for more information.
*
*  @author    PitchPrint Inc <hello@pitchprint.com>
*  @copyright 2023 PitchPrint Inc
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PitchPrint Inc.
*/
function getBrowser()
{
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version = '';
    $ub = '';

    // First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'linux';
    } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'mac';
    } elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'windows';
    }
    // Next get the name of the useragent yes seperately and for good reason
    if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
        $bname = 'Internet Explorer';
        $ub = 'MSIE';
    }
    if (preg_match('/Trident\/7.0; rv:11.0/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
        $bname = 'Internet Explorer';
        $ub = 'MSIE';
    } elseif (preg_match('/Firefox/i', $u_agent)) {
        $bname = 'Mozilla Firefox';
        $ub = 'Firefox';
    } elseif (preg_match('/Chrome/i', $u_agent)) {
        $bname = 'Google Chrome';
        $ub = 'Chrome';
    } elseif (preg_match('/Safari/i', $u_agent)) {
        $bname = 'Apple Safari';
        $ub = 'Safari';
    } elseif (preg_match('/Opera/i', $u_agent)) {
        $bname = 'Opera';
        $ub = 'Opera';
    } elseif (preg_match('/Netscape/i', $u_agent)) {
        $bname = 'Netscape';
        $ub = 'Netscape';
    }

    // finally get the correct version number
    $known = ['Version', $ub, 'other'];
    $pattern = '#(?<browser>' . join('|', $known) .
    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }

    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        // we will have two since we are not using 'other' argument yet
        // see if version is before or after the name
        if (strripos($u_agent, 'Version') < strripos($u_agent, $ub)) {
            $version = $matches['version'][0];
        } else {
            $version = isset($matches['version'][1]) ? $matches['version'][1] : null;
            // 			print_r($matches);
        }
    } else {
        $version = $matches['version'][0];
    }

    // check if we have a number
    if ($version == null || $version == '') {
        $version = '?';
    }

    return [
        'userAgent' => $u_agent,
        'name' => $bname,
        'version' => $version,
        'platform' => $platform,
        'pattern' => $pattern,
    ];
}

function browserValid()
{
    $browser = getBrowser();
    $isValid = true;
    switch ($browser['name']) {
        case 'Unknown':
        case 'Internet Explorer':
            $isValid = false;
            break;
        case 'Apple Safari':
            if (strpos($browser['userAgent'], 'CPU OS 9') !== false || strpos($browser['userAgent'], 'CPU OS 8') !== false || strpos($browser['userAgent'], 'CPU OS 7') !== false) {
                $isValid = false;
            }
            break;
        case 'Google Chrome':
            if ($browser['version'] < 57) {
                $isValid = false;
            }
            break;
        case 'Mozilla Firefox':
            if ($browser['version'] < 52) {
                $isValid = false;
            }
            break;
    }

    return $isValid;
}
