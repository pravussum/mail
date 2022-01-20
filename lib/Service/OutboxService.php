<?php

declare(strict_types=1);

/**
 * Mail App
 *
 * @copyright 2022 Anna Larch <anna.larch@gmx.net>
 *
 * @author Anna Larch <anna.larch@gmx.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mail\Service;

use OCA\Mail\Account;
use OCA\Mail\Address;
use OCA\Mail\AddressList;
use OCA\Mail\Contracts\ILocalMailbox;
use OCA\Mail\Contracts\IMailTransmission;
use OCA\Mail\Db\LocalMailboxMessage;
use OCA\Mail\Db\LocalMailboxMessageMapper;
use OCA\Mail\Db\Recipient;
use OCA\Mail\Exception\SentMailboxNotSetException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Model\NewMessageData;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class OutboxService implements ILocalMailbox {

	/** @var IMailTransmission */
	private $transmission;

	/** @var LoggerInterface */
	private $logger;

	/** @var LocalMailboxMessageMapper */
	private $mapper;

	public function __construct(IMailTransmission $transmission, LoggerInterface $logger, LocalMailboxMessageMapper $mapper) {
		$this->transmission = $transmission;
		$this->logger = $logger;
		$this->mapper = $mapper;
	}

	/**
	 * @throws ServiceException
	 */
	public function getMessages(string $userId): array {
		try {
			return $this->mapper->getAllForUser($userId);
		} catch (Exception $e) {
			throw new ServiceException('', 0, $e);
		}
	}

	/**
	 * @throws ServiceException
	 */
	public function getMessage(int $id): LocalMailboxMessage {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			throw new ServiceException('Could not fetch any messages', 400);
		}
	}

	/**
	 * @throws ServiceException
	 */
	public function deleteMessage(LocalMailboxMessage $message): void {
		try {
			$this->mapper->delete($message);
		} catch (Exception $e) {
			throw new ServiceException('Could not delete message' . $e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws ServiceException
	 */
	public function sendMessage(LocalMailboxMessage $message, Account $account): bool {
		$related = $this->mapper->getRelatedData($message->getId(), $account->getUserId());
		$recipients = $related['recipients'];
		$to = new AddressList(
			array_filter($recipients, static function ($recipient) {
				if (Recipient::TYPE_TO === $recipient['type']) {
					Address::fromRaw($recipient['label'], $recipient['email']);
				}
			}));
		$cc = new AddressList(
			array_filter($recipients, static function ($recipient) {
				if (Recipient::TYPE_CC === $recipient['type']) {
					Address::fromRaw($recipient['label'], $recipient['email']);
				}
			}));
		$bcc = new AddressList(
			array_filter($recipients, static function ($recipient) {
				if (Recipient::TYPE_BCC === $recipient['type']) {
					Address::fromRaw($recipient['label'], $recipient['email']);
				}
			}));
		$messageData = new NewMessageData(
			$account, $to, $cc, $bcc, $message->getSubject(), $message['body'], $related['attachments'], $message->isHtml(), $message->isMdn()
		);
		try {
			if (!$message->isMdn()) {
				$this->transmission->sendMessage($messageData);
				$this->mapper->delete($message);
			} else {
				// wot? How do I MDN?
				throw new ServiceException('Not implemented', 400);
			}
		} catch (SentMailboxNotSetException | Exception $e) {
			throw new ServiceException('Could not send message' . $e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @throws ServiceException
	 */
	public function saveMessage(LocalMailboxMessage $message, array $recipients, array $attachmentIds = []): LocalMailboxMessage {
		try {
			$this->mapper->insert($message);
		} catch (Exception $e) {
			throw new ServiceException('Could not save message', 400);
		}
		$this->mapper->saveRelatedData($message, $recipients, $attachmentIds);
	}
}
