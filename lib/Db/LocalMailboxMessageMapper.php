<?php

declare(strict_types=1);

/**
 * @copyright 2022 Anna Larch <anna@nextcloud.com>
 *
 * @author 2022 Anna Larch <anna@nextcloud.com>
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
 */

namespace OCA\Mail\Db;

use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DBException;
use function array_map;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Tag>
 */
class LocalMailboxMessageMapper extends QBMapper {
	/** @var RecipientMapper */
	private $mapper;

	/** @var MailAccountMapper */
	private $accountMapper;

	/** @var LocalAttachmentMapper */
	private $attachmentMapper;

	public function __construct(IDBConnection $db,
								MailAccountMapper $accountMapper,
								LocalAttachmentMapper $attachmentMapper,
								RecipientMapper $recipientMapper) {
		parent::__construct($db, 'mail_local_mailbox');
		$this->mapper = $recipientMapper;
		$this->accountMapper = $accountMapper;
		$this->attachmentMapper = $attachmentMapper;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws DBException
	 */
	public function getAllForUser(string $userId): array {
		$accountIds = array_map(static function ($account) {
			return $account['id'];
		}, $this->accountMapper->findByUserId($userId));

		$qb = $this->db->getQueryBuilder();
		$qb->select('m.*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('account_id', $qb->createNamedParameter($accountIds, IQueryBuilder::PARAM_INT_ARRAY), IQueryBuilder::PARAM_INT_ARRAY)
			);
		$result = $qb->execute();

		$messages = array_map(function (array $row) use ($userId) {
			$row['attachments'] = $this->attachmentMapper->findForLocalMailbox($row['id'], $userId);
			$row['recipients'] = $this->mapper->findRecipients($row['id'], Recipient::TYPE_OUTBOX);
			return $row;
		}, $result->fetchAll());
		$result->closeCursor();

		return $messages;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws DBException
	 */
	public function find(int $id): LocalMailboxMessage {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT)
			);
		return $this->mapper->findEntity($qb);
	}

	/**
	 */
	public function getRelatedData(int $id, string $userId): array {
		$related = [];
		$related['attachments'] = $this->attachmentMapper->findForLocalMailbox($id, $userId);
		$related['recipients'] = $this->mapper->findRecipients($id, Recipient::TYPE_OUTBOX);
		return $related;
	}

	public function saveRelatedData(LocalMailboxMessage $message, array $recipients, array $attachmentIds = []): array {
		//Todo
		return [];
	}
}
