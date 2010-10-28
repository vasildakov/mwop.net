<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Markup
 * @subpackage Parser
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Markup;

/**
 * @category   Zend
 * @package    Zend_Markup
 * @subpackage Parser
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Parser
{
    /**
     * Parse a string
     *
     * @param  string $value
     *
     * @return array
     */
    public function parse($value);

    /**
     * Build a tree with a certain strategy
     *
     * @param array $tokens
     * @param string $strategy
     *
     * @return \Zend\Markup\TokenList
     */
    public function buildTree(array $tokens, $strategy = 'default');

    /**
     * Tokenize a string
     *
     * @param string $value
     *
     * @return array
     */
    public function tokenize($value);
}
