/* 
 * Copyright (C) 2022 K4-713 <k4@hownottospellwinnebago.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

CREATE TABLE mons (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(32) DEFAULT NULL,
  dex_national int(11) DEFAULT NULL,
  variant varchar(16) DEFAULT NULL,
  type1 varchar(16) DEFAULT NULL,
  type2 varchar(16) DEFAULT NULL,
  ability1 varchar(32) DEFAULT NULL,
  ability2 varchar(32) DEFAULT NULL,
  ability_hidden varchar(32) DEFAULT NULL,
  legendary BOOLEAN DEFAULT NULL,
  mythical BOOLEAN DEFAULT NULL,
  b_hp varchar(16) DEFAULT NULL,
  b_att varchar(16) DEFAULT NULL,
  b_def varchar(16) DEFAULT NULL,
  b_sp_att varchar(16) DEFAULT NULL,
  b_sp_def varchar(16) DEFAULT NULL,
  b_speed varchar(16) DEFAULT NULL,
  female decimal(4, 2) DEFAULT NULL,
  male decimal(4, 2) DEFAULT NULL,
  egg_groups varchar(32) DEFAULT NULL,
  dex_kalos_central int(11) DEFAULT NULL,
  dex_kalos_coastal int(11) DEFAULT NULL,
  dex_kalos_mountain int(11) DEFAULT NULL,
  dex_hoenn int(11) DEFAULT NULL,
  dex_alola_ultra int(11) DEFAULT NULL,
  dex_galar int(11) DEFAULT NULL,
  dex_galar_isle int(11) DEFAULT NULL,
  dex_galar_crown int(11) DEFAULT NULL,
  dex_sinnoh_bdsp int(11) DEFAULT NULL,
  dex_hisui int(11) DEFAULT NULL,
  catchable_xy BOOLEAN DEFAULT NULL,
  catchable_oras BOOLEAN DEFAULT NULL,
  catchable_usum BOOLEAN DEFAULT NULL,
  catchable_swsh BOOLEAN DEFAULT NULL,
  catchable_bdsp BOOLEAN DEFAULT NULL,
  catchable_hisui BOOLEAN DEFAULT NULL,
  PRIMARY KEY (id)
);

-- CREATE TABLE inventory (
--   neo_id int(11) DEFAULT NULL,
--   shop_qty int(11) DEFAULT NULL,
--   sdb_qty int(11) DEFAULT NULL,
--   unload BOOLEAN DEFAULT NULL,
--   UNIQUE KEY neo_id (neo_id)
-- );
