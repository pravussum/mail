<?php

declare(strict_types=1);

namespace OCA\Mail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20220104144742 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$localMailboxTable = $schema->createTable('mail_local_mailbox');
		$localMailboxTable->addColumn('id', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 4,
		]);
		$localMailboxTable->addColumn('type', 'integer', [
			'notnull' => true,
			'unsigned' => true,
			'length' => 1,
		]);
		$localMailboxTable->addColumn('account_id', 'integer', [
			'notnull' => true,
			'length' => 4,
		]);
		$localMailboxTable->addColumn('send_at', 'string', [
			'notnull' => false,
			'length' => 4
		]);
		$localMailboxTable->addColumn('text', 'text', [
			'notnull' => true,
			'length' => 16777215, // I think MEDIUMTEXT should be fine for an RFC822/message.
		]);

		$localMailboxTable->setPrimaryKey(['id']);

		return $schema;
	}

}
