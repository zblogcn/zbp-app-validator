<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/29
 * Time: 14:17
 */

namespace Zsxsoft\AppValidator\Tasks;


use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeDumper;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\PathHelper;
use Zsxsoft\AppValidator\Helpers\ZBPHelper;
use Zsxsoft\AppValidator\Tasks\StaticCodeScanner\ErrorOutputter;
use Zsxsoft\AppValidator\Tasks\StaticCodeScanner\NameChecker;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class ScanStaticCode
{
    private $_file = "";
    private $_path = "";

    /**
     * Check unsafe functions
     */
    public function checkFunctions()
    {
        $outputter = new ErrorOutputter(PathHelper::getDisplayFilename($this->_path));
        $checker = new NameChecker();
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor($checker);
        $nodeDumper = new NodeDumper;
        try {
            $stmts = $parser->parse($this->_file);
            $stmts = $traverser->traverse($stmts);
            foreach ($checker->ret as $item) {
                $outputter->{$item['type'] . '_'}($item);
            }
        } catch (\PhpParser\Error $e) {
            $extension = pathinfo($this->_path, PATHINFO_EXTENSION);
            if (strtolower($extension) !== 'php') {
                return;
            }
            Logger::error("Parse " . PathHelper::getDisplayFilename($this->_path) . " Error.");
            Logger::error($e->getMessage());
        }
    }

    /**
     * Check Order By Rand
     */
    public function checkOrderByRand()
    {
        $regex = "/[\"']rand\(\)[\"'][ \t]*?\=\>[\"'][ \t]*?[\"']|ORDER[ \t]*BY[\t ]*rand\(/i";
        $matches = null;
        if (preg_match($regex, $this->_file)) {
            Logger::warning('Maybe using rand() in MySQL in ' . PathHelper::getDisplayFilename($this->_path));
            Logger::warning('You should remove it.');
        }
    }

    /**
     * Check if is a page added CSRF Token
     */
    public function checkCSRFToken()
    {
        if (preg_match("/<form/", $this->_file)) {
            $regex = "/(GetCSRFToken|BuildSafeURL|BuildSafeCmdURL)/i";
            if (!preg_match($regex, $this->_file)) {
                $app = ZBPWrapper::getApp();
                if ($app->type === 'theme' && preg_match('/(template|compiled)\/.*php$/i', $this->_path)) {
                    return;
                }
                if (preg_match('/\.(js|css|txt)$/i', $this->_path)) {
                    return;
                }
                Logger::warning('Maybe no CSRF protection in backend!');
                Logger::warning(PathHelper::getDisplayFilename($this->_path));
            }
        }
    }


    /**
     * Run Checker
     *
     * @param string $filePath
     */
    public function runChecker($filePath)
    {
        // Logger::info("Scanning $filePath");
        $this->_path = $filePath;
        $this->_file = file_get_contents($this->_path);
        $this->checkOrderByRand();
        $this->checkFunctions();
        $this->checkCSRFToken();
    }

    /**
     * Run
     */
    public function run()
    {
        $zbp = ZBPWrapper::getZbp();
        $app = ZBPWrapper::getApp();
        $pluginDir = ZBPHelper::getPath() . '/zb_users/' . $app->type . '/' . $app->id . '/';
        foreach (PathHelper::scanDirectory($pluginDir) as $index => $value) {
            $this->runChecker(PathHelper::getAbsoluteFilename($value));
        }
        if ($app->type === 'theme') {
            $compiledDir = ZBPHelper::getPath() . '/zb_users/cache/compiled/' . $app->id . '/';
            if (!is_dir($compiledDir)) {
                return;
            }
            foreach (PathHelper::scanDirectory($compiledDir) as $index => $value) {
                $this->runChecker(PathHelper::getAbsoluteFilename($value));
            }
        }
    }
}
