<?php
/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Forum.Topic.Poll
 *
 * @copyright     Copyright (C) 2008 - 2020 Kunena Team. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/

namespace Joomla\Component\Kunena\Libraries\Forum\Topic\Poll;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use function defined;

/**
 * Class KunenaForumTopicPollHelper
 *
 * @since   Kunena 6.0
 */
abstract class Helper
{
	/**
	 * @var     array
	 * @since   Kunena 6.0
	 */
	protected static $_instances = [];

	/**
	 * Returns KunenaForumTopic object.
	 *
	 * @param   int   $identifier  The poll to load - Can be only an integer.
	 * @param   bool  $reload      reload
	 *
	 * @return  KunenaForumTopicPoll
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public static function get($identifier = null, $reload = false)
	{
		if ($identifier instanceof KunenaForumTopicPoll)
		{
			return $identifier;
		}

		$id = intval($identifier);

		if ($id < 1)
		{
			return new KunenaForumTopicPoll;
		}

		if ($reload || empty(self::$_instances [$id]))
		{
			self::$_instances [$id] = new KunenaForumTopicPoll($id);
		}

		return self::$_instances [$id];
	}

	/**
	 * @return  void
	 *
	 * @since   Kunena 6.0
	 */
	public static function recount()
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->update($db->quoteName('#__kunena_topics', 'a'))
			->innerJoin($db->quoteName('#__kunena_polls', 'b') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('b.threadid'))
			->set($db->quoteName('a.poll_id') . ' = ' . $db->quoteName('b.id'));

		$db->setQuery($query);
		$db->execute();
	}
}
