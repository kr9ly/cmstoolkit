<?php

namespace core\view;

use core\Config;
use core\Controller;

class TwigView implements Instance {
	private $loader;
	private $twig;
	
	public function init() {
		$this->loader = new \Twig_Loader_Filesystem(VIEW_PATH);
		$this->twig = new \Twig_Environment($this->loader,array(
			'cache' => Config::get('twig.cache_path')
		));
		$this->twig->addTokenParser(new Project_Snippet_TokenParser());
		$this->twig->addTokenParser(new Project_Js_TokenParser());
		$this->twig->addTokenParser(new Project_JsOutput_TokenParser());
	}
	
	public function render($path, Controller $controller) {
		return $this->twig->render($path, array(
				'controller' => $controller
				,'meta' => $controller->getMetadata()
				,'param' => $controller->getParameter()));
	}
}

class Project_Snippet_TokenParser extends \Twig_TokenParser
{
	public function parse(\Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$lineno = $token->getLine();
		$name = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
		$alias = null;
		
		if ($stream->test('as')) {
			$stream->next();
			$alias = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
		}

		$this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

		return new Project_Snippet_Node($name, $alias, $lineno, $this->getTag());
	}

	public function getTag()
	{
		return 'snippet';
	}
}

class Project_Snippet_Node extends \Twig_Node
{
	public function __construct($name, $alias, $lineno, $tag = null)
	{
		parent::__construct(array(), array('name' => $name, 'alias' => $alias), $lineno, $tag);
	}

	public function compile(\Twig_Compiler $compiler)
	{
		$compiler
		->addDebugInfo($this)
		->write('$context[\'snippets\'][\''.($this->getAttribute('alias') ?: $this->getAttribute('name')).'\'] = core\\Snippet::get(\''.$this->getAttribute('name').'\');' . "\n")
		->write('$context[\'snippets\'][\''.($this->getAttribute('alias') ?: $this->getAttribute('name')).'\']->init($context[\'controller\']);' . "\n")
		->write('$context[\'snippets\'][\''.($this->getAttribute('alias') ?: $this->getAttribute('name')).'\']->execute();' . "\n")
		;
	}
}

class Project_Js_TokenParser extends \Twig_TokenParser
{
	public function parse(\Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$lineno = $token->getLine();
		$src = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();

		$this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

		return new Project_Js_Node($src, $lineno, $this->getTag());
	}

	public function getTag()
	{
		return 'js';
	}
}

class Project_Js_Node extends \Twig_Node
{
	public function __construct($src, $lineno, $tag = null)
	{
		parent::__construct(array(), array('src' => $src), $lineno, $tag);
	}

	public function compile(\Twig_Compiler $compiler)
	{
		$compiler
		->addDebugInfo($this)
		->write('$context[\'js\'][] = \''.$this->getAttribute('src').'\';' . "\n")
		;
	}
}

class Project_JsOutput_TokenParser extends \Twig_TokenParser
{
	public function parse(\Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$lineno = $token->getLine();

		$this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

		return new Project_JsOutput_Node($lineno, $this->getTag());
	}

	public function getTag()
	{
		return 'js_output';
	}
}

class Project_JsOutput_Node extends \Twig_Node
{
	public function __construct($lineno, $tag = null)
	{
		parent::__construct(array(), array(), $lineno, $tag);
	}

	public function compile(\Twig_Compiler $compiler)
	{
		$compiler
		->addDebugInfo($this)
		->write('foreach (core\\Arr::get($context, \'js\', array()) as $src) {' . "\n")
		->indent()
		->write('echo \'<script src="\'.$src.\'"></script>\'."\\n";')
		->outdent()
		->write('}' . "\n")
		;
	}
}