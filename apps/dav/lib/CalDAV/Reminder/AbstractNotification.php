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

use \DateTime;
use \DateTimeImmutable;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10NFactory;
use OCP\IUser;
use Sabre\VObject\Component\VCalendar;

abstract class AbstractNotification
{
    /** @var ILogger */
    protected $logger;

    /** @var L10NFactory */
    protected $l10nFactory;

    /** @var IURLGenerator */
    protected $urlGenerator;

    /** @var IL10N */
    protected $l10n;

    /** @var string */
    protected $lang;

    /**
	 * @param IMailer $mailer
	 * @param ILogger $logger
	 * @param L10NFactory $l10nFactory
	 * @param IUrlGenerator $urlGenerator
	 * @param IDBConnection $db
	 */
	public function __construct(ILogger $logger, L10NFactory $l10nFactory, IURLGenerator $urlGenerator) {
		$this->logger = $logger;
		$this->l10nFactory = $l10nFactory;
        $this->urlGenerator = $urlGenerator;
		
    }

    /**
     * Set lang for notifications
     * @param string $lang
     * @return AbstractNotification
     */
    public function setLang(IL10N $l10n): AbstractNotification
    {
        $this->l10n = $l10n;
        return $this;
    }

    /**
	 * Send notification
	 *
	 * @param array $event
	 * @return void
	 */
    public function send(array $event, VCalendar $vcalendar, IUser $user): void {}
}
