ALTER TABLE `uw_collection_contents` 
	ADD INDEX `versioned_collection` (`remove_transaction_id`, `collection_id`, `member_mid`),
	ADD INDEX `versioned_collection_member` (`remove_transaction_id`, `member_mid`, `collection_id`),
	ADD INDEX `versioned_internal_id` (`remove_transaction_id`, `internal_member_id` (255), `collection_id`, `member_mid`);
	
--	ADD INDEX `unversioned_collection` (`collection_id`, `member_mid`),
--	ADD INDEX `unversioned_collection_member` (`member_mid`, `collection_id`),
--	ADD INDEX `unversioned_internal_id` (`internal_member_id` (255), `collection_id`, `member_mid`);