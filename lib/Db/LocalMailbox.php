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

use JsonSerializable;
use OCA\Mail\Model\NewMessageData;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getType()
 * @method void setType(int $type)
 * @method int getAccountId()
 * @method void setAccountId(int $accountId)
 * @method int getSendAt()
 * @method void setSendAt(int $sendAt)
 * @method string getText()
 * @method void setText(string $rfc822message)
 */
class LocalMailbox extends Entity implements JsonSerializable {

	private $type;
	private $accountId;
	private $sendAt;
	private $text;

	public CONST OUTGOING = 0;
	public CONST DRAFT = 1;

	public function __construct() {
		$this->addType('text', 'string');
	}
	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'type' => $this->getType(),
			'accountId' => $this->getAccountId(),
			'send_at' => $this->getSendAt(),
			'text' => $this->getText(),
		];
	}
}
