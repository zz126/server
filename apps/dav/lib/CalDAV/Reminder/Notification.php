<?php
/**
 * @copyright Copyright (c) 2018 Thomas Citharel <tcit@tcit.fr>
 * 
 * @author Thomas Citharel <tcit@tcit.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\CalDAV\Reminder;

use OCA\DAV\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Notification\IManager;
use OCP\IUser;
use Sabre\VObject\Component\VCalendar;

class Notification extends AbstractNotification
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var IConfig
     */
    private $config;

    /**
	 * @param IConfig $config
	 * @param ILogger $logger
     * @param IManager $manager
	 * @param L10NFactory $l10nFactory
	 * @param IUrlGenerator $urlGenerator
	 */
	public function __construct(IConfig $config, IManager $manager, ILogger $logger,
								L10NFactory $l10nFactory,
								IURLGenerator $urlGenerator) {
		parent::__construct($logger, $l10nFactory, $urlGenerator);
        $this->config = $config;
        $this->manager = $manager;
    }
    
    /**
	 * Send notification
	 *
	 * @param array $event
	 * @return void
	 */
    public function send(array $event, VCalendar $vcalendar, IUser $user): void
    {
    /** @var INotification $notification */
		$notification = $this->manager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($user->getUID())
			//->setDateTime(\DateTime::createFromFormat('U', $reminder['notificationdate']))
			->setDateTime(new \DateTime())
			->setObject(Application::APP_ID, $event['uid']) // $type and $id
			->setSubject('calendar_reminder', ['title' => $event['title'], 'start' => $event['start']->getTimestamp()]) // $subject and $parameters
			->setMessage('calendar_reminder', ['when' => $event['when'], 'description' => $event['description'], 'location' => $event['location']])
		;
		$this->manager->notify($notification);
    }
}