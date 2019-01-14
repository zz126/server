<?php
/**
 * @author Thomas Citharel <tcit@tcit.fr>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\BackgroundJob;

use \DateTime;
use OC\BackgroundJob\Job;
use OCA\DAV\CalDAV\Reminder\Backend;
use OCA\DAV\CalDAV\Reminder\EmailNotification;
use OCA\DAV\CalDAV\Reminder\Notification;
use OCP\L10N\IFactory as L10NFactory;
use OCP\IUserManager;
use OCP\IConfig;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Reader;

class EventReminderJob extends Job {

	/** @var Notification */
	private $notification;

	/** @var EmailNotification */
	private $emailNotification;

	/** @var Backend */
	private $backend;

	/** @var L10NFactory */
	private $l10nFactory;

	/** @var IL10N */
	private $l10n;

	/** @var IUserManager */
	private $userManager;

	/** @var IConfig */
	private $config;

	public function __construct(Backend $backend,
								EmailNotification $emailNotification,
								Notification $notification,
								L10NFactory $l10nFactory,
								IUserManager $userManager,
								IConfig $config) {
		$this->notification = $notification;
		$this->backend = $backend;
		$this->emailNotification = $emailNotification;
		$this->l10nFactory = $l10nFactory;
		$this->userManager = $userManager;
		$this->config = $config;

		/** Run every 15 minutes */
		// $this->setInterval(10);
	}

	/**
	 * @param $arg
	 */
	public function run($arg) {
		$reminders = $this->backend->getRemindersToProcess();

		error_log('reminder background job run');
		foreach ($reminders as $reminder) {
			error_log('running reminder');
			$calendarData = Reader::read($reminder['calendardata']);

			if ($reminder['type'] === 'EMAIL') {
				$notification = $this->emailNotification;
			} elseif ($reminder['type'] === 'DISPLAY') {
				$notification = $this->notification;
			} else {
				break;
			}

			$user = $this->userManager->get($reminder['uid']);

			$lang = $this->config->getUserValue($user->getUID(), 'core', 'lang', $this->l10nFactory->findLanguage());

			$this->l10n = $this->l10nFactory->get('dav', $lang);

			$event = $this->extractEventDetails($calendarData);
			var_dump($event);

			$notification
				->setLang($this->l10n)
				->send($event, $calendarData, $user);
			$this->backend->removeReminder($reminder['id']);
		}
	}

	/**
	 * @var VCalendar $vcalendar
	 * @var string $defaultValue
	 * @return array
	 */
    private function extractEventDetails(VCalendar $vcalendar, $defaultValue = '--')
    {
        $vevent = $vcalendar->VEVENT;

		$start = $vevent->DTSTART;
		if (isset($vevent->DTEND)) {
			$end = $vevent->DTEND;
		} elseif (isset($vevent->DURATION)) {
			$isFloating = $vevent->DTSTART->isFloating();
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->add(DateTimeParser::parse($vevent->DURATION->getValue()));
			$end->setDateTime($endDateTime, $isFloating);
		} elseif (!$vevent->DTSTART->hasTime()) {
			$isFloating = $vevent->DTSTART->isFloating();
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->modify('+1 day');
			$end->setDateTime($endDateTime, $isFloating);
		} else {
			$end = clone $vevent->DTSTART;
		}

        return [
            'title' => (string) $vevent->SUMMARY ?: $defaultValue,
            'description' => (string) $vevent->DESCRIPTION ?: $defaultValue,
            'start'=> $start->getDateTime(),
            'end' => $end->getDateTime(),
            'when' => $this->generateWhenString($start, $end),
            'url' => (string) $vevent->URL ?: $defaultValue,
            'location' => (string) $vevent->LOCATION ?: $defaultValue,
            'uid' => (string) $vevent->UID,
        ];
    }

	/**
	 * @param Property $dtstart
	 * @param Property $dtend
	 */
	private function generateWhenString(Property $dtstart, Property $dtend)
	{
		$isAllDay = $dtstart instanceof \Property\ICalendar\Date;

		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtstart */
		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtend */
		/** @var DateTimeImmutable $dtstartDt */
		$dtstartDt = $dtstart->getDateTime();
		/** @var DateTimeImmutable $dtendDt */
		$dtendDt = $dtend->getDateTime();

		$diff = $dtstartDt->diff($dtendDt);

		$dtstartDt = new DateTime($dtstartDt->format(DateTime::ATOM));
		$dtendDt = new DateTime($dtendDt->format(DateTime::ATOM));

		if ($isAllDay) {
			// One day event
			if ($diff->days === 1) {
				return $this->l10n->l('date', $dtstartDt, ['width' => 'medium']);
			}

			//event that spans over multiple days
			$localeStart = $this->l10n->l('date', $dtstartDt, ['width' => 'medium']);
			$localeEnd = $this->l10n->l('date', $dtendDt, ['width' => 'medium']);

			return $localeStart . ' - ' . $localeEnd;
		}

		/** @var Property\ICalendar\DateTime $dtstart */
		/** @var Property\ICalendar\DateTime $dtend */
		$isFloating = $dtstart->isFloating();
		$startTimezone = $endTimezone = null;
		if (!$isFloating) {
			$prop = $dtstart->offsetGet('TZID');
			if ($prop instanceof Parameter) {
				$startTimezone = $prop->getValue();
			}

			$prop = $dtend->offsetGet('TZID');
			if ($prop instanceof Parameter) {
				$endTimezone = $prop->getValue();
			}
		}

		$localeStart = $this->l10n->l('weekdayName', $dtstartDt, ['width' => 'abbreviated']) . ', ' .
			$this->l10n->l('datetime', $dtstartDt, ['width' => 'medium|short']);

		// always show full date with timezone if timezones are different
		if ($startTimezone !== $endTimezone) {
			$localeEnd = $this->l10n->l('datetime', $dtendDt, ['width' => 'medium|short']);

			return $localeStart . ' (' . $startTimezone . ') - ' .
				$localeEnd . ' (' . $endTimezone . ')';
		}

		// show only end time if date is the same
		if ($this->isDayEqual($dtstartDt, $dtendDt)) {
			$localeEnd = $this->l10n->l('time', $dtendDt, ['width' => 'short']);
		} else {
			$localeEnd = $this->l10n->l('weekdayName', $dtendDt, ['width' => 'abbreviated']) . ', ' .
				$this->l10n->l('datetime', $dtendDt, ['width' => 'medium|short']);
		}

		return  $localeStart . ' - ' . $localeEnd . ' (' . $startTimezone . ')';
	}

	/**
	 * @param DateTime $dtStart
	 * @param DateTime $dtEnd
	 * @return bool
	 */
    private function isDayEqual(DateTime $dtStart, DateTime $dtEnd): bool
    {
		return $dtStart->format('Y-m-d') === $dtEnd->format('Y-m-d');
	}

	// private function getDetails(array $reminder)
	// {
	// 	$component = null;

	// 	/**
	// 	 * Get the real event
	// 	 */
	// 	foreach($reminder['eventData']->getComponents() as $component) {
	// 		/** @var Component $component */
	// 		if ($component->name === 'VEVENT') {
	// 			break;
	// 		}
	// 	}

	// 	/**
	// 	 * Try to get geocoordinates
	// 	 */
	// 	$geo = null;
	// 	if (isset($component->GEO)) {
	// 		list($geo['lat'], $geo['long']) = explode(';', $component->GEO, 2);
	// 	}

	// 	/**
	// 	 * Build the list of attendees
	// 	 */


	// 	return [
	// 		'title' => (string) $component->SUMMARY,
	// 		'start' => $component->DTSTART->getDateTime(),
	// 		'location' => $component->LOCATION,
	// 		'geo' => $geo,
	// 		'description' => $component->DESCRIPTION,
	// 		'calendarName' => $reminder['displayname'],
	// 		'participants' => $component->ATTENDEE,
	// 		'notificationdate' => $reminder['notificationdate'],
	// 		'uri' => $reminder['objecturi'],
	// 	];
	// }
}