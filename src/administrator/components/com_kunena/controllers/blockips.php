<?php
/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Controllers
 *
 * @copyright       Copyright (C) 2008 - 2020 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/

namespace Joomla\Component\Kunena\Administrator;

defined('_JEXEC') or die();

use Exception;
use function defined;

/**
 * Kunena Backend Block Ips Controller
 *
 * @since   Kunena 5.1
 */
class KunenaAdminControllerBlockips extends KunenaController
{
	/**
	 * @var     string
	 * @since   Kunena 5.1
	 */
	protected $baseurl = null;

	/**
	 * Construct
	 *
	 * @param   array  $config  config
	 *
	 * @since   Kunena 5.1
	 *
	 * @throws  Exception
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		$this->baseurl = 'administrator/index.php?option=com_kunena&view=blockips';
	}
}
