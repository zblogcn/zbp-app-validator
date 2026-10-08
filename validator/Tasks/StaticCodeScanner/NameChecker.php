<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/29
 * Time: 14:43
 */

namespace Zsxsoft\AppValidator\Tasks\StaticCodeScanner;

use PhpParser\Node;
use PhpParser\Node\Stmt;

class NameChecker extends \PhpParser\NodeVisitorAbstract
{
    public $ret = [];
    public function leaveNode(Node $node)
    {
        $name = null;

        if ($node instanceof Node\Name) {
            $name = $node->toString();
        } elseif ($node instanceof Stmt\Function_) {
            $name = $node->namespacedName->toString();
        } elseif ($node instanceof Node\Expr\Eval_) {
            $name = 'eval';
        } else if ($node instanceof Node\Expr\ShellExec) {
            $name = 'shell_exec';
        }
        if (is_null($name)) {
            return $node;
        }

        $line = $node->getStartLine();
        $type = '';
        $data = '';
        if (in_array($name, ['curl_init'])) {
            $type = 'curl';
        } else if (in_array($name, ['eval', 'assert', 'create_function'])) {
            $type = 'eval';
        } else if (in_array($name, ['exec', 'system', 'popen', 'proc_open', 'pcntl_exec', 'passthru', 'shell_exec'])) {
            $type = 'system';
            $data = $name;
        } else if (in_array($name, ['session_start', 'session_write_close'])) {
            $type = 'session';
            $data = $name;
        }

        if ($type !== '') {
            $this->ret[] = ['type' => $type, 'line' => $line, 'data' => $data];
        }

        return $node;
    }
}
