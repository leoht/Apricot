<?php

namespace Apricot\Component;

trait View {

	public static function view($name, array $vars = array())
	{
		$apricot = self::getInstance();

		require $apricot->basePath . '/views/' . $name . '.php';
	}
}