<?php
/**
 * A file browser extension that exposes Bolt's /files directory to the front-end,
 * implementing a file-manager-like UI in the front-end.
 *
 * @author Tobias Dammers <tobias@twokings.nl>
 */

namespace FileBrowser;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;

class Extension extends \Bolt\BaseExtension
{
    public function info()
    {
        return array(
            'name' => "FileBrowser",
            'description' => "Exposes /files on the front-end",
            'author' => "Tobias Dammers",
            'link' => "http://bolt.cm",
            'version' => "0.1",
            'required_bolt_version' => "1.6.0",
            'type' => "General",
            'first_releasedate' => null,
            'latest_releasedate' => null,
            'priority' => 10
        );
    }

    public function initialize()
    {
        $this->addTwigFunction('file_browser', 'twigFileBrowser');
        $this->app->get("/async/file_browser", array($this, "asyncGetFiles"))->bind("file_browser_get");

        /*
        $this->app->get("/waffles", array($this, 'show_waffles'))->bind('show_waffles');
        $this->app->post("/waffles/add", array($this, 'add_waffles'))->bind('add_waffles');
        $this->app->post("/waffles/clear", array($this, 'clear_waffles'))->bind('clear_waffles');
        */
    }

    private function validateMode($mode) {
        $allowedModes = array('list', 'icons');
        if (!in_array($mode, $allowedModes)) {
            $imploded = implode(', ', $allowedModes);
            throw new \Exception("Invalid mode: $mode, allowed modes are: $imploded");
        }
    }

    private function listFiles($rootPath, $currentPath) {
        $path = '';
        if (!empty($rootPath)) {
            $path .= "$rootPath/";
        }
        if (!empty($currentPath)) {
            $path .= "$currentPath/";
        }
        $finder = new Finder();
        $files =
            $finder
                ->depth('== 0')
                ->notName('*.exe')
                ->notName('*.php')
                ->notName('*.html')
                ->notName('*.js')
                ->sortByName()
                ->sortByType()
                ->in($this->app['paths']['filespath'] . "/$path");
        return iterator_to_array($files);
    }

    private function splitPath($path) {
        $f = function($part) {
            // No whitespace around components
            $part = trim($part);
            // No empty components
            if (empty($part)) {
                return false;
            }
            // No hidden components
            if ($part[0] === '.') {
                return false;
            }
            return true;
        };
        return array_filter(explode('/', $path), $f);
    }

    private function getContext($mode, $rootPath, $currentPath) {
        $rootPP = $this->splitPath($rootPath);
        $currentPP = $this->splitPath($currentPath);
        if (count($currentPP)) {
            $upPP = $currentPP;
            array_pop($upPP);
        }
        else {
            $upPP = null;
        }

        return array(
            'mode' => $mode,
            'paths' => array(
                'root' => implode('/', $rootPP),
                'current' => implode('/', $currentPP),
                'up' => ($upPP === null) ? null : implode('/', $upPP)
            ),
            'files' => $this->listFiles($rootPath, $currentPath));
    }

    public function asyncGetFiles(Request $request) {
        $mode = ($request->get('fb_mode') ? $request->get('fb_mode') : 'list');
        $this->validateMode($mode);
        $rootPath = ($request->get('fb_root') ? $request->get('fb_root') : '');
        $currentPath = ($request->get('fb_cp') ? $request->get('fb_cp') : '');
        $context = $this->getContext($mode, $rootPath, $currentPath);
        return $this->render("list.twig", $context);
    }

    public function twigFileBrowser($mode = 'list', $rootPath = '', $currentPath = '')
    {
        $this->validateMode($mode);
        $fbcp = $this->app['request']->get('fb_cp');
        if (!empty($fbcp)) {
            $currentPath = $fbcp;
        }
        $context = $this->getContext($mode, $rootPath, $currentPath);
        $inner = $this->render("list.twig", $context);
        $context['inner'] = $inner;
        return new \Twig_Markup($this->render("container.twig", $context), 'UTF-8');
    }

    private function render($template, $data) {
        $this->app['twig.loader.filesystem']->addPath(dirname(__FILE__) . '/templates');
        return new \Twig_Markup($this->app['render']->render($template, $data), 'UTF-8');
    }

}

