-- Farm photos belong to a farm, not a warehouse, so warehouse_id must be optional.
ALTER TABLE `images` MODIFY `warehouse_id` INT NULL;
