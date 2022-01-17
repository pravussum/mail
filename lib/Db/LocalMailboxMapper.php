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

use function array_map;
use function array_chunk;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * @template-extends QBMapper<Tag>
 */
class LocalMailboxMapper extends QBMapper {

	/** @var IL10N */
	private $l10n;

	/** @var RecipientMapper */
	private $mapper;
	/** @var MailAccountMapper */
	private $accountMapper;
	/** @var LocalAttachmentMapper */
	private $attachmentMapper;

	public function __construct(IDBConnection $db,
								IL10N         $l10n,
	MailAccountMapper $accountMapper,
	LocalAttachmentMapper $attachmentMapper,
	RecipientMapper                           $recipientMapper) {
		parent::__construct($db, 'mail_local_mailbox');
		$this->l10n = $l10n;
		$this->mapper = $recipientMapper;
		$this->accountMapper = $accountMapper;
		$this->attachmentMapper = $attachmentMapper;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getAllForUser(string $userId): array {
		$accountIds =  array_map(static function($account) {
			return $account['id'];
		},$this->accountMapper->findByUserId($userId));


		$qb = $this->db->getQueryBuilder();

		$qb->select('m.*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('account_id', $qb->createNamedParameter($accountIds, IQueryBuilder::PARAM_INT_ARRAY), IQueryBuilder::PARAM_INT_ARRAY)
			);

		$result = $qb->execute();

		$messages =  array_map(function(array $row) use ($userId) {
			$row['attachments'] = $this->attachmentMapper->findForLocalMailbox($row['id'], $userId);
			$row['recipients'] = $this->mapper->findRecipients($row['id'], Recipient::TYPE_OUTBOX);
			return $row;
		}, $result->fetchAll());
		$result->closeCursor();

		return $messages;
	}
}
