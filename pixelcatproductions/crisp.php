<?php

/*
 * Copyright (C) 2021 Justin René Back <justin@tosdr.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace crisp;

/**
 * Core class, nothing else
 *
 * @author Justin Back <jback@pixelcatproductions.net>
 */
class core {
    /* Some important constants */

    const CRISP_VERSION = "3.0.0";
    
    const API_VERSION = "1.0.0";

    /**
     * This is my autoloader. 
     * There are many like it, but this one is mine. 
     * My autoloader is my best friend. 
     * It is my life. 
     * I must master it as I must master my life. 
     * My autoloader, without me, is useless. 
     * Without my autoloader, I am useless. 
     * I must use my autoloader true. 
     * I must code better than my enemy who is trying to be better than me.
     * I must be better than him before he is. 
     * And I will be.
     *
     */
    public static function bootstrap() {
        spl_autoload_register(function ($class) {
            $file = __DIR__ . "/class/" . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

            if (file_exists($file)) {
                require $file;
                return true;
            }
            return false;
        });
        /** Core headers, can be accessed anywhere */
        header("X-Cluster: " . gethostname());
        /** After autoloading we include additional headers below */
    }

}

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    core::bootstrap();
    if (php_sapi_name() !== 'cli') {

        $GLOBALS["route"] = api\Helper::processRoute($_GET["route"]);

        $GLOBALS["microtime"] = array();
        $GLOBALS["microtime"]["logic"] = array();
        $GLOBALS["microtime"]["template"] = array();

        $GLOBALS["microtime"]["logic"]["start"] = microtime(true);

        $GLOBALS["plugins"] = array();
        $GLOBALS['hook'] = array();
        $GLOBALS['navbar'] = array();
        $GLOBALS['navbar_right'] = array();
        $GLOBALS["render"] = array();

        session_start();

        $CurrentTheme = \crisp\api\Config::get("theme");
        $CurrentFile = substr(substr($_SERVER['PHP_SELF'], 1), 0, -4);
        $CurrentPage = $GLOBALS["route"]->Page;
        $CurrentPage = ($CurrentPage == "" ? "frontpage" : $CurrentPage);
        $CurrentPage = explode(".", $CurrentPage)[0];
        $Simple = (explode('.', $_SERVER["HTTP_HOST"])[0] === "simple" ? true : false);

        if (isset($_GET["universe"])) {
            Universe::changeUniverse($_GET["universe"]);
        } elseif (!isset($_COOKIE[core\Config::$Cookie_Prefix . "universe"])) {
            Universe::changeUniverse(Universe::UNIVERSE_PUBLIC);
        }

        define("CURRENT_UNIVERSE", Universe::getUniverse($_COOKIE[core\Config::$Cookie_Prefix . "universe"]));
        define("CURRENT_UNIVERSE_NAME", Universe::getUniverseName(CURRENT_UNIVERSE));

        $ThemeLoader = new \Twig\Loader\FilesystemLoader(array(__DIR__ . "/../themes/$CurrentTheme/templates/", __DIR__ . "/../plugins/"));
        $TwigTheme;

        if (CURRENT_UNIVERSE <= Universe::UNIVERSE_BETA) {
            if (!$Simple) {
                $TwigTheme = new \Twig\Environment($ThemeLoader, [
                    'cache' => __DIR__ . '/cache/'
                ]);
            } else {
                $TwigTheme = new \Twig\Environment($ThemeLoader, [
                    'cache' => __DIR__ . '/cache/simple/'
                ]);
            }
        } else {
            $TwigTheme = new \Twig\Environment($ThemeLoader, []);
        }


        api\Helper::setLocale();
        $Locale = \crisp\api\Helper::getLocale();

        if (CURRENT_UNIVERSE >= Universe::UNIVERSE_BETA) {
            if (isset($_GET["test_theme_component"])) {
                core\Themes::setThemeMode($_GET["test_theme_component"]);
            }
        } else {
            core\Themes::setThemeMode("0");
        }

        header("X-CMS-CurrentPage: $CurrentPage");
        header("X-CMS-Locale: $Locale");
        header("X-CMS-Universe: " . CURRENT_UNIVERSE);
        header("X-CMS-Universe-Human: " . CURRENT_UNIVERSE_NAME);

        $TwigTheme->addGlobal("config", \crisp\api\Config::list());
        $TwigTheme->addGlobal("locale", $Locale);
        $TwigTheme->addGlobal("languages", \crisp\api\Translation::listLanguages(false));
        $TwigTheme->addGlobal("GET", $_GET);
        $TwigTheme->addGlobal("UNIVERSE", CURRENT_UNIVERSE);
        $TwigTheme->addGlobal("UNIVERSE_NAME", CURRENT_UNIVERSE_NAME);
        $TwigTheme->addGlobal("CurrentPage", $CurrentPage);
        $TwigTheme->addGlobal("POST", $_POST);
        $TwigTheme->addGlobal("SERVER", $_SERVER);
        $TwigTheme->addGlobal("GLOBALS", $GLOBALS);
        $TwigTheme->addGlobal("COOKIE", $_COOKIE);
        $TwigTheme->addGlobal("SIMPLE", $Simple);
        $TwigTheme->addGlobal("isMobile", \crisp\api\Helper::isMobile());
        $TwigTheme->addGlobal("URL", api\Helper::currentDomain());
        $TwigTheme->addGlobal("CLUSTER", gethostname());
        $TwigTheme->addGlobal("THEME_MODE", \crisp\core\Themes::getThemeMode());

        $TwigTheme->addExtension(new \Twig\Extension\StringLoaderExtension());

        $TwigTheme->addFunction(new \Twig\TwigFunction('getGitRevision', [new \crisp\api\Helper(), 'getGitRevision']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('getService', [new \crisp\api\Phoenix(), 'getServicePG']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('getPoint', [new \crisp\api\Phoenix(), 'getPointPG']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('getPointsByService', [new \crisp\api\Phoenix(), 'getPointsByServicePG']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('getCase', [new \crisp\api\Phoenix(), 'getCasePG']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('getGitBranch', [new \crisp\api\Helper(), 'getGitBranch']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('prettyDump', [new \crisp\api\Helper(), 'prettyDump']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('microtime', 'microtime'));
        $TwigTheme->addFunction(new \Twig\TwigFunction('includeResource', [new \crisp\core\Themes(), 'includeResource']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('generateLink', [new \crisp\api\Helper(), 'generateLink']));

        /* CSRF Stuff */
        $TwigTheme->addFunction(new \Twig\TwigFunction('csrf', [new \crisp\core\Security(), 'getCSRF']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('refreshCSRF', [new \crisp\core\Security(), 'regenCSRF']));
        $TwigTheme->addFunction(new \Twig\TwigFunction('validateCSRF', [new \crisp\core\Security(), 'matchCSRF']));

        $Translation = new \crisp\api\Translation($Locale);

        $TwigTheme->addFilter(new \Twig\TwigFilter('date', 'date'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('bcdiv', 'bcdiv'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('integer', 'intval'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('double', 'doubleval'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('json', 'json_decode'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('json_encode', 'json_encode'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('json_decode', 'json_decode'));
        $TwigTheme->addFilter(new \Twig\TwigFilter('translate', [$Translation, 'fetch']));
        $TwigTheme->addFilter(new \Twig\TwigFilter('getlang', [new \crisp\api\lists\Languages(), 'getLanguageByCode']));
        $TwigTheme->addFilter(new \Twig\TwigFilter('truncateText', [new \crisp\api\Helper(), 'truncateText']));

        $EnvFile = parse_ini_file(__DIR__ . "/../.env");

        $RedisClass = new \crisp\core\Redis();
        $rateLimiter = new \RateLimit\RedisRateLimiter($RedisClass->getDBConnector());

        if (file_exists(__DIR__ . "/../themes/$CurrentTheme/hook.php")) {
            require_once __DIR__ . "/../themes/$CurrentTheme/hook.php";
        }

        if (explode("/", $_GET["route"])[1] === "api" || isset($_SERVER["IS_API_ENDPOINT"])) {

            header('Access-Control-Allow-Origin: *');
            header("Cache-Control: max-age=600, public, must-revalidate");

            if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] == "i am not valid") {
                http_response_code(403);
                echo $TwigTheme->render("errors/nginx/403.twig", ["error_msg" => "Request forbidden by administrative rules. Please make sure your request has a User-Agent header"]);
                exit;
            }

            $Query = (isset($GLOBALS["route"]->GET["q"]) ? $GLOBALS["route"]->GET["q"] : $GLOBALS["route"]->GET["service"]);

            if (strpos($Query, ".json")) {
                $Query = substr($Query, 0, -5);
            }

            if (strlen($Query) === 0) {
                $Query = "no_query";
            }
            $keyDetails;
            if (isset(apache_request_headers()["Authorization"])) {
                $keyDetails = api\Helper::getAPIKeyDetails(apache_request_headers()["Authorization"]);

                if ($keyDetails["expires_at"] !== null && strtotime($keyDetails["expires_at"]) < time()) {
                    header("X-APIKey: expired");
                } elseif ($keyDetails["revoked"]) {
                    header("X-APIKey: revoked");
                } else {
                    header("X-APIKey: ok");
                }
            } else {
                header("X-APIKey: not-given");
            }

            if (isset(apache_request_headers()["Authorization"]) && !api\Helper::getAPIKey()) {
                http_response_code(401);
                echo $TwigTheme->render("errors/nginx/401.twig", ["error_msg" => "Request forbidden by administrative rules. Please make sure your request has a valid Authorization header"]);
                exit;
            }

            $Benefit = "Guest";
            $IndicatorSecond = "s_" . \crisp\api\Helper::getRealIpAddr();
            $IndicatorHour = "h_" . \crisp\api\Helper::getRealIpAddr();
            $IndicatorDay = "d_" . \crisp\api\Helper::getRealIpAddr();

            $LimitSecond = \RateLimit\Rate::perSecond(15);
            $LimitHour = \RateLimit\Rate::perHour(1000);
            $LimitDay = \RateLimit\Rate::perHour(15000);

            if (CURRENT_UNIVERSE == \crisp\Universe::UNIVERSE_TOSDR || in_array(\crisp\api\Helper::getRealIpAddr(), \crisp\api\Config::get("office_ips"))) {

                $LimitSecond = \RateLimit\Rate::perSecond(15000);
                $LimitHour = \RateLimit\Rate::perHour(100000);
                $LimitDay = \RateLimit\Rate::perHour(150000);

                $Benefit = "Staff";
                if (in_array(\crisp\api\Helper::getRealIpAddr(), \crisp\api\Config::get("office_ips"))) {
                    $Benefit = "Office";
                }
            }
            $apikey = api\Helper::getAPIKey();
            if ($apikey) {

                $LimitSecond;
                $LimitHour;
                $LimitDay;
                $Benefit;
                if ($keyDetails["ratelimit_second"] === null) {
                    $LimitSecond = \RateLimit\Rate::perSecond(150);
                } else {
                    $LimitSecond = \RateLimit\Rate::perSecond($keyDetails["ratelimit_second"]);
                }
                if ($keyDetails["ratelimit_hour"] === null) {
                    $LimitHour = \RateLimit\Rate::perHour(10000);
                } else {
                    $LimitHour = \RateLimit\Rate::perHour($keyDetails["ratelimit_hour"]);
                }

                if ($keyDetails["ratelimit_day"] === null) {
                    $LimitDay = \RateLimit\Rate::perHour(50000);
                } else {
                    $LimitDay = \RateLimit\Rate::perHour($keyDetails["ratelimit_day"]);
                }

                if ($keyDetails["ratelimit_benefit"] === null) {
                    $Benefit = "Partner";
                } else {
                    $Benefit = $keyDetails["ratelimit_benefit"];
                }
            }

            $statusSecond = $rateLimiter->limitSilently($IndicatorSecond, $LimitSecond);
            $statusHour = $rateLimiter->limitSilently($IndicatorHour, $LimitHour);
            $statusDay = $rateLimiter->limitSilently($IndicatorDay, $LimitDay);

            header("X-CMS-CDN: " . api\Config::get("cdn"));
            header("X-CMS-SHIELDS: " . api\Config::get("shield_cdn"));
            header("X-RateLimit-Benefit: " . $Benefit);
            header("X-RateLimit-S: " . $statusSecond->getRemainingAttempts());
            header("X-RateLimit-H: " . $statusHour->getRemainingAttempts());
            header("X-RateLimit-D: " . $statusDay->getRemainingAttempts());
            header("X-RateLimit-Benefit: " . $Benefit);
            header("X-CMS-API: " . api\Config::get("api_cdn"));

            if ($statusSecond->limitExceeded() || $statusHour->limitExceeded() || $statusDay->limitExceeded()) {
                http_response_code(429);
                echo $TwigTheme->render("errors/nginx/429.twig", ["error_msg" => "Request forbidden by administrative rules. You are sending too many requests in a certain timeframe."]);
                exit;
            }



            core\Themes::loadAPI($TwigTheme, $GLOBALS["route"]->Page, $Query);
            core\Plugins::loadAPI($GLOBALS["route"]->Page, $QUERY);

            exit;
        }

        if (!$GLOBALS["route"]->Language) {
            header("Location: /$Locale/$CurrentPage");
            exit;
        }

        \crisp\core\Plugins::load($TwigTheme, $CurrentFile, $CurrentPage);
        \crisp\core\Themes::load($TwigTheme, $CurrentFile, $CurrentPage);
    }
} catch (\crisp\exceptions\BitmaskException $ex) {
    http_response_code(500);
    $errorraw = file_get_contents(__DIR__ . "/../themes/emergency/error.html");
    try {
        echo strtr($errorraw, array("{{ exception }}" => api\ErrorReporter::create(500, $ex->getTraceAsString(), $ex->getMessage() . "\n\n" . api\Helper::currentURL(), $ex->getCode() . "_")));
    } catch (\Exception $ex2) {
        echo strtr($errorraw, array("{{ exception }}" => $ex->getCode()));
        exit;
    }
} catch (\TypeError | \Exception | \Error | \CompileError | \ParseError | \Throwable $ex) {
    http_response_code(500);
    $errorraw = file_get_contents(__DIR__ . "/../themes/emergency/error.html");
    try {
        echo strtr($errorraw, array("{{ exception }}" => api\ErrorReporter::create(500, $ex->getTraceAsString(), $ex->getMessage() . "\n\n" . api\Helper::currentURL(), "ca_")));
        exit;
    } catch (\Exception $ex) {
        echo strtr($errorraw, array("{{ exception }}" => "An error occurred... reporting the error?!?"));
        exit;
    }
} catch (\Twig\Error\LoaderError $ex) {
    http_response_code(500);
    $errorraw = file_get_contents(__DIR__ . "/../themes/emergency/error.html");
    try {
        echo strtr($errorraw, array("{{ exception }}" => api\ErrorReporter::create(500, $ex->getTraceAsString(), $ex->getMessage() . "\n\n" . api\Helper::currentURL(), crisp\core\Bitmask::TWIG_ERROR . "_")));
        exit;
    } catch (\Exception $ex) {
        echo strtr($errorraw, array("{{ exception }}" => "An error occurred... reporting the error?!?"));
        exit;
    }
}
