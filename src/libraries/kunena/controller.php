<?php
/**
 * Kunena Component
 *
 * @package        Kunena.Framework
 *
 * @copyright      Copyright (C) 2008 - 2020 Kunena Team. All rights reserved.
 * @license        https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link           https://www.kunena.org
 **/

namespace Joomla\Component\Kunena\Libraries;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Application\CMSApplicationInterface;
use function defined;

/**
 * Class KunenaController
 *
 * @since   Kunena 6.0
 */
class Controller extends BaseController
{
	/**
	 * @var     CMSApplicationInterface
	 * @since   Kunena 6.0
	 */
	protected $app;

	/**
	 * @var     KunenaUser|null
	 * @since   Kunena 6.0
	 */
	public $me = null;

	/**
	 * @var     Config|null
	 * @since   Kunena 6.0
	 */
	public $config = null;

	/**
	 * @param   array  $config  config
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		$this->profiler = KunenaProfiler::instance('Kunena');
		$this->me       = KunenaUserHelper::getMyself();

		// Save user profile if it didn't exist.
		if ($this->me->userid && !$this->me->exists())
		{
			$this->me->save();
		}

		if (empty($this->input))
		{
			$this->input = $this->app->input;
		}
	}

	/**
	 * Method to get the appropriate controller.
	 *
	 * @param   string  $prefix  prefix
	 * @param   mixed   $config  config
	 *
	 * @return  KunenaController
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public static function getInstance($prefix = 'Kunena', $config = [])
	{
		static $instance = null;

		if (!$prefix)
		{
			$prefix = 'Kunena';
		}

		if (!empty($instance) && !isset($instance->home))
		{
			return $instance;
		}

		$input = Factory::getApplication()->input;

		$app     = Factory::getApplication();
		$command = $input->get('task', 'display');

		// Check for a controller.task command.
		if (strpos($command, '.') !== false)
		{
			// Explode the controller.task command.
			list($view, $task) = explode('.', $command);

			// Reset the task without the controller context.
			$input->set('task', $task);
		}
		else
		{
			// Base controller.
			$view = strtolower(Factory::getApplication()->input->getWord('view', $app->isClient('administrator') ? 'cpanel' : 'home'));
		}

		$path = JPATH_COMPONENT . "/controllers/{$view}.php";

		// If the controller file path exists, include it ... else die with a 500 error.
		if (is_file($path))
		{
			require_once $path;
		}
		else
		{
			throw new Exception(Text::sprintf('COM_KUNENA_INVALID_CONTROLLER', ucfirst($view)), 404);
		}

		// Set the name for the controller and instantiate it.
		if ($app->isClient('administrator'))
		{
			$class = $prefix . 'AdminController' . ucfirst($view);
			KunenaFactory::loadLanguage('com_kunena.controllers', 'admin');
			KunenaFactory::loadLanguage('com_kunena.models', 'admin');
			KunenaFactory::loadLanguage('com_kunena.sys', 'admin');
			KunenaFactory::loadLanguage('com_kunena', 'site');
		}
		else
		{
			$class = $prefix . 'Controller' . ucfirst($view);
			KunenaFactory::loadLanguage('com_kunena.controllers');
			KunenaFactory::loadLanguage('com_kunena.models');
			KunenaFactory::loadLanguage('com_kunena.sys', 'admin');
		}

		if (class_exists($class))
		{
			$instance = new $class;
		}
		else
		{
			throw new Exception(Text::sprintf('COM_KUNENA_INVALID_CONTROLLER_CLASS', $class), 404);
		}

		return $instance;
	}

	/**
	 * Calls a task and creates HTML or JSON response from it.
	 *
	 * If response is in HTML, we just redirect and enqueue message if there's an exception.
	 * NOTE: legacy display task is a special case and reverts to original Joomla behavior.
	 *
	 * If response is in JSON, we return JSON response, which follows Joomla\CMS\Response\JsonResponse with some extra
	 * data:
	 *
	 * Default:   {code, location=null, success, message, messages, data={step, location, html}}
	 * Redirect:  {code, location=[string], success, message, messages=null, data}
	 * Exception: {code, location=[null|string], success=false, message, messages, data={exceptions=[{code,
	 * message}...]}}
	 *
	 * code = [int]: Usually HTTP status code, but can also error code from the exception (informal only).
	 * location = [null|string]: If set, JavaScript should always redirect to another page.
	 * success = [bool]: Determines whether the request (or action) was successful. Can be false without being an
	 * error.
	 * message = [string|null]: The main response message.
	 * messages = [array|null]: Array of enqueue'd messages.
	 * data = [mixed]: The response data.
	 *
	 * @param   string  $task  Task to be run.
	 *
	 * @return  void
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 * @throws  null
	 */
	public function execute($task)
	{
		if (!$task)
		{
			$task = 'display';
		}

		$app          = Factory::getApplication();
		$this->format = $this->input->getWord('format', 'html');

		try
		{
			// TODO: This would be great, but we would need to store POST before doing it in here...
			/*
			if ($task != 'display')
			{
				// Make sure that Kunena is online before running any tasks (doesn't affect admins).
				if (!KunenaForum::enabled(true))
				{
					throw new KunenaExceptionAuthorise(Text::_('COM_KUNENA_FORUM_IS_OFFLINE'), 503);
				}

				// If forum is for registered users only, prevent guests from accessing tasks.
				if ($this->config->regonly && !$this->me->exists())
				{
					throw new KunenaExceptionAuthorise(Text::_('COM_KUNENA_LOGIN_NOTIFICATION'), 403);
				}
			}
			*/

			// Execute the task.
			$content = static::executeTask($task);
		}
		catch (Exception $e)
		{
			$content = $e;
		}

		// Legacy view support.
		if ($task == 'display')
		{
			if ($content instanceof Exception)
			{
				throw $content;
			}

			return;
		}

		// Create HTML redirect.
		if ($this->format == 'html')
		{
			if ($content instanceof Exception)
			{
				$app->enqueueMessage($content->getMessage(), 'error');

				if (!$this->redirect)
				{
					// On exceptions always return back to the referrer page.
					$this->setRedirect(KunenaRoute::getReferrer());
				}
			}

			// The following code gets only called for successful tasks.
			if (!$this->redirect)
			{
				// If controller didn't set a new redirect, try if request has return url in it.
				$return = base64_decode($app->input->getBase64('return'));

				// Only allow internal urls to be used.
				if ($return && Uri::isInternal($return))
				{
					$redirect = Route::_($return, false);
				}
				// Otherwise return back to the referrer.
				else
				{
					$redirect = KunenaRoute::getReferrer();
				}

				$this->setRedirect($redirect);
			}

			return;
		}

		// Otherwise tell the browser that our response is in JSON.
		header('Content-type: application/json', true);

		// Create JSON response and set the redirect.
		$response           = new KunenaResponseJson($content, null, false, !empty($this->redirect));
		$response->location = $this->redirect;

		// In case of an error we want to set HTTP error code.
		if ($content instanceof Exception)
		{
			// We want to wrap the exception to be able to display correct HTTP status code.
			$exception = new KunenaExceptionAuthorise($content->getMessage(), $content->getCode(), $content);
			header('HTTP/1.1 ' . $exception->getResponseStatus(), true);
		}

		echo json_encode($response);

		// It's much faster and safer to exit now than let Joomla to send the response.
		Factory::getApplication()->close();
	}

	/**
	 * Execute task (slightly modified from Joomla).
	 *
	 * @param   string  $task  task
	 *
	 * @return  mixed
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	protected function executeTask($task)
	{
		$dot        = strpos($task, '.');
		$this->task = $dot ? substr($task, $dot + 1) : $task;

		$task = strtolower($this->task);

		if (isset($this->taskMap[$this->task]))
		{
			$doTask = $this->taskMap[$this->task];
		}
		elseif (isset($this->taskMap['__default']))
		{
			$doTask = $this->taskMap['__default'];
		}
		else
		{
			throw new Exception(Text::sprintf('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND', $task), 404);
		}

		// Record the actual task being fired
		$this->doTask = $doTask;

		return $this->$doTask();
	}

	/**
	 * Method to display a view.
	 *
	 * @param   boolean     $cachable   If true, the view output will be cached
	 * @param   array|bool  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                                  {@link \Joomla\CMS\Filter\InputFilter::clean()}.
	 *
	 * @return  BaseController  A Joomla\CMS\MVC\Controller\BaseController object to
	 *                                                     support chaining.
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 * @throws  null
	 */
	public function display($cachable = false, $urlparams = false)
	{
		KUNENA_PROFILER ? $this->profiler->mark('beforeDisplay') : null;
		KUNENA_PROFILER ? KunenaProfiler::instance()->start('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

		// Get the document object.
		$document = Factory::getApplication()->getDocument();

		// Set the default view name and format from the Request.
		$vName   = Factory::getApplication()->input->getWord('view', $this->app->isClient('administrator') ? 'cpanel' : 'home');
		$lName   = Factory::getApplication()->input->getWord('layout', 'default');
		$vFormat = $document->getType();

		if ($this->app->isClient('administrator'))
		{
			// Load admin language files
			KunenaFactory::loadLanguage('com_kunena.install', 'admin');
			KunenaFactory::loadLanguage('com_kunena.views', 'admin');

			// Load last to get deprecated language files to work
			KunenaFactory::loadLanguage('com_kunena', 'admin');

			// Version warning, disable J4 for now.
			require_once KPATH_ADMIN . '/install/version.php';
			$version         = new KunenaVersion;
			$version_warning = $version->getVersionWarning();
		}
		else
		{
			// Load site language files
			KunenaFactory::loadLanguage('com_kunena.views');
			KunenaFactory::loadLanguage('com_kunena.templates');

			// Load last to get deprecated language files to work
			KunenaFactory::loadLanguage('com_kunena');

			$menu   = $this->app->getMenu();
			$active = $menu->getActive();

			// Check if menu item was correctly routed
			$routed = $menu->getItem(KunenaRoute::getItemID());

			if ($vFormat == 'html' && !empty($routed->id) && (empty($active->id) || $active->id != $routed->id))
			{
				// Routing has been changed, redirect
				// FIXME: check possible redirect loops!
				$route    = KunenaRoute::_(null, false);
				$activeId = !empty($active->id) ? $active->id : 0;
				Log::add("Redirect from " . Uri::getInstance()->toString(['path', 'query']) . " ({$activeId}) to {$route} ($routed->id)", Log::DEBUG, 'kunena');
				$this->app->redirect($route);
			}

			// Joomla 2.5+ multi-language support
			/*
			// FIXME:
			if (isset($active->language) && $active->language != '*') {
				$language = Factory::getApplication()->getDocument()->getLanguage();
				if (strtolower($active->language) != strtolower($language)) {
					$route = KunenaRoute::_(null, false);
					Log::add("Language redirect from ".Uri::getInstance()->toString(array('path', 'query'))." to {$route}", Log::DEBUG, 'kunena');
					$this->redirect ($route);
				}
			}
			*/
		}

		$view = $this->getView($vName, $vFormat);

		if ($view)
		{
			if ($this->app->isClient('site') && $vFormat == 'html')
			{
				$common = $this->getView('common', $vFormat);
				$model  = $this->getModel('common');
				$common->setModel($model, true);
				$view->ktemplate = $common->ktemplate = KunenaFactory::getTemplate();
				$view->common    = $common;
			}

			// Set the view layout.
			$view->setLayout($lName);

			// Get the appropriate model for the view.
			$model = $this->getModel($vName);

			// Push the model into the view (as default).
			$view->setModel($model, true);

			// Push document object into the view.
			$view->document = $document;

			// Render the view.
			if ($vFormat == 'html')
			{
				PluginHelper::importPlugin('kunena');
				Factory::getApplication()->triggerEvent('onKunenaDisplay', ['start', $view]);
				$view->displayAll();
				Factory::getApplication()->triggerEvent('onKunenaDisplay', ['end', $view]);
			}
			else
			{
				$view->displayLayout();
			}
		}

		KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

		return $this;
	}

	/**
	 * Escapes a value for output in a view script.
	 *
	 * @param   string  $var  The output to escape.
	 *
	 * @return  string The escaped value.
	 *
	 * @since   Kunena 6.0
	 */
	public function escape($var)
	{
		return htmlspecialchars($var, ENT_COMPAT, 'UTF-8');
	}

	/**
	 * @return  string
	 *
	 * @since   Kunena 6.0
	 */
	public function getRedirect()
	{
		return $this->redirect;
	}

	/**
	 * @return  string
	 *
	 * @since   Kunena 6.0
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * @return  string
	 *
	 * @since   Kunena 6.0
	 */
	public function getMessageType()
	{
		return $this->messageType;
	}

	/**
	 * Redirect back to the referrer page.
	 *
	 * If there's no referrer or it's external, Kunena will return to the default page.
	 * Also redirects back to tasks are prevented.
	 *
	 * @param   string  $default  default
	 * @param   string  $anchor   anchor
	 *
	 * @return  void
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  null
	 * @throws  Exception
	 */
	protected function setRedirectBack($default = null, $anchor = null)
	{
		$this->setRedirect(KunenaRoute::getReferrer($default, $anchor));
	}
}
