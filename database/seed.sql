-- Seed data taken from the original McRelish site. Run after schema.sql.
SET NAMES utf8mb4;

INSERT INTO menu_sections (id, name, subtitle, sort_order, visible) VALUES
('daily', 'Daily Meals', 'Fresh every day at our Awka Food Hub', 0, 1),
('bulk', 'Bulk Home Orders', 'Large portions for family & small gatherings', 1, 1),
('boxes', 'Food Boxes', 'Curated meal boxes made for gifting', 2, 1);

INSERT INTO menu_items (id, section_id, name, description, price, available, visible, sort_order, config) VALUES
('mcrelish-jollof', 'daily', 'McRelish Special Jollof', 'Rich tomato blend and perfectly seasoned rice. Pick your protein, then add extras and sides your way.', 1800, 1, 1, 0, '{"meals":[{"id":"egg","label":"Special Jollof & Egg","price":1800},{"id":"beef","label":"Special Jollof & Beef","price":2000},{"id":"chicken","label":"Special Jollof & Chicken","price":3000},{"id":"turkey","label":"Special Jollof & Turkey","price":4000},{"id":"jumbo-turkey","label":"Special Jollof & Jumbo Turkey","price":5000},{"id":"no-protein","label":"Special Jollof only — no protein","price":1500},{"id":"none","label":"No rice — extras & sides only","price":0}],"extras":[{"id":"rice","label":"Extra Rice","price":1000,"group":"Extras","max":1},{"id":"chicken","label":"Extra Chicken","price":1500,"group":"Extras","soloPrice":2000},{"id":"beef","label":"Extra Beef","price":500,"group":"Extras"},{"id":"egg","label":"Extra Egg","price":300,"group":"Extras"},{"id":"turkey","label":"Extra Turkey","price":2500,"group":"Extras"},{"id":"jumbo-turkey","label":"Extra Jumbo Turkey","price":3500,"group":"Extras"},{"id":"water","label":"Water","price":300,"group":"Extras"},{"id":"plantain","label":"Plantain","price":500,"group":"Sides"},{"id":"coleslaw","label":"Coleslaw","price":500,"group":"Sides","soloPrice":1000}]}'),
('jolly-fest', 'daily', 'Jolly Fest Feast', 'A plate of McRelish Special Jollof, 1 peppered chicken, 2 pieces of peppered beef, 1 portion of plantain, 1 coleslaw, 1 bottled water and 1 zobo drink.', 5500, 1, 1, 1, NULL),
('special-fried-rice', 'daily', 'McRelish Special Fried Rice', 'Colorful fried rice with fresh vegetables. Pick your protein, then add extras and sides your way.', 1800, 0, 1, 2, '{"meals":[{"id":"egg","label":"Special Fried Rice & Egg","price":1800},{"id":"beef","label":"Special Fried Rice & Beef","price":2000},{"id":"chicken","label":"Special Fried Rice & Chicken","price":3000},{"id":"turkey","label":"Special Fried Rice & Turkey","price":4000},{"id":"jumbo-turkey","label":"Special Fried Rice & Jumbo Turkey","price":5000},{"id":"no-protein","label":"Special Fried Rice only — no protein","price":1500},{"id":"none","label":"No rice — extras & sides only","price":0}],"extras":[{"id":"rice","label":"Extra Rice","price":1000,"group":"Extras","max":1},{"id":"chicken","label":"Extra Chicken","price":1500,"group":"Extras","soloPrice":2000},{"id":"beef","label":"Extra Beef","price":500,"group":"Extras"},{"id":"egg","label":"Extra Egg","price":300,"group":"Extras"},{"id":"turkey","label":"Extra Turkey","price":2500,"group":"Extras"},{"id":"jumbo-turkey","label":"Extra Jumbo Turkey","price":3500,"group":"Extras"},{"id":"water","label":"Water","price":300,"group":"Extras"},{"id":"plantain","label":"Plantain","price":500,"group":"Sides"},{"id":"coleslaw","label":"Coleslaw","price":500,"group":"Sides","soloPrice":1000}]}'),
('solo-chicken', 'daily', 'Chicken — Solo Plate', 'Our well-seasoned chicken served on its own, no rice.', 2000, 1, 1, 3, NULL),
('solo-coleslaw', 'daily', 'Coleslaw — Solo Plate', 'Fresh, creamy coleslaw served on its own.', 1000, 1, 1, 4, NULL),
('family-jollof-pack', 'bulk', 'Family Jollof Pack', 'Jollof Rice with your choice of chicken, turkey, or beef, prepared in a family-size portion.', 7500, 1, 1, 5, '{"options":[{"id":"protein","label":"Choice of protein","choices":[{"id":"chicken","label":"Chicken"},{"id":"turkey","label":"Turkey"},{"id":"beef","label":"Beef"}]}]}'),
('deluxe-fried-rice', 'bulk', 'Deluxe Fried Rice', 'Mixed vegetables and your choice of protein.', 7500, 1, 1, 6, '{"options":[{"id":"protein","label":"Choice of protein","choices":[{"id":"chicken","label":"Chicken"},{"id":"turkey","label":"Turkey"},{"id":"beef","label":"Beef"}]}]}'),
('coconut-rice-pack', 'bulk', 'Coconut Rice Pack', 'Aromatic coconut-infused rice with protein options.', 8000, 1, 1, 7, '{"options":[{"id":"protein","label":"Choice of protein","choices":[{"id":"chicken","label":"Chicken"},{"id":"turkey","label":"Turkey"},{"id":"beef","label":"Beef"}]}]}'),
('classic-box', 'boxes', 'The Classic Box', 'Includes Jollof Rice, Sides, and choice of protein.', 4500, 1, 1, 8, '{"options":[{"id":"protein","label":"Choice of protein","choices":[{"id":"chicken","label":"Chicken"},{"id":"turkey","label":"Turkey"},{"id":"beef","label":"Beef"}]}]}'),
('premium-box', 'boxes', 'The Premium Box', 'Includes Deluxe Fried Rice, Sides, and choice of protein.', 6000, 1, 1, 9, '{"options":[{"id":"protein","label":"Choice of protein","choices":[{"id":"chicken","label":"Chicken"},{"id":"turkey","label":"Turkey"},{"id":"beef","label":"Beef"}]}]}'),
('gourmet-box', 'boxes', 'The Gourmet Box', 'Includes Coconut Rice, Sides, and premium Proteins.', 7500, 1, 1, 10, NULL),
('celebration-box', 'boxes', 'The Celebration Box', 'Includes Native Rice, Deluxe Sides, and Proteins.', 9000, 1, 1, 11, NULL);

INSERT INTO menu_prices (pkey, product_name, label, price, available, sort) VALUES
('p:mcrelish-jollof', 'McRelish Special Jollof', 'McRelish Special Jollof', 1800, 1, 0),
('m:mcrelish-jollof:egg', 'McRelish Special Jollof', 'Special Jollof & Egg', 1800, 1, 101),
('m:mcrelish-jollof:beef', 'McRelish Special Jollof', 'Special Jollof & Beef', 2000, 1, 102),
('m:mcrelish-jollof:chicken', 'McRelish Special Jollof', 'Special Jollof & Chicken', 3000, 1, 103),
('m:mcrelish-jollof:turkey', 'McRelish Special Jollof', 'Special Jollof & Turkey', 4000, 1, 104),
('m:mcrelish-jollof:jumbo-turkey', 'McRelish Special Jollof', 'Special Jollof & Jumbo Turkey', 5000, 1, 105),
('m:mcrelish-jollof:no-protein', 'McRelish Special Jollof', 'Special Jollof only — no protein', 1500, 1, 106),
('e:mcrelish-jollof:rice', 'McRelish Special Jollof', 'Extra Rice', 1000, 1, 107),
('e:mcrelish-jollof:chicken', 'McRelish Special Jollof', 'Extra Chicken', 1500, 1, 108),
('s:mcrelish-jollof:chicken', 'McRelish Special Jollof', 'Extra Chicken — on its own', 2000, 1, 109),
('e:mcrelish-jollof:beef', 'McRelish Special Jollof', 'Extra Beef', 500, 1, 110),
('e:mcrelish-jollof:egg', 'McRelish Special Jollof', 'Extra Egg', 300, 1, 111),
('e:mcrelish-jollof:turkey', 'McRelish Special Jollof', 'Extra Turkey', 2500, 1, 112),
('e:mcrelish-jollof:jumbo-turkey', 'McRelish Special Jollof', 'Extra Jumbo Turkey', 3500, 1, 113),
('e:mcrelish-jollof:water', 'McRelish Special Jollof', 'Water', 300, 1, 114),
('e:mcrelish-jollof:plantain', 'McRelish Special Jollof', 'Plantain', 500, 1, 115),
('e:mcrelish-jollof:coleslaw', 'McRelish Special Jollof', 'Coleslaw', 500, 1, 116),
('s:mcrelish-jollof:coleslaw', 'McRelish Special Jollof', 'Coleslaw — on its own', 1000, 1, 117),
('p:special-fried-rice', 'McRelish Special Fried Rice', 'McRelish Special Fried Rice', 1800, 0, 2),
('m:special-fried-rice:egg', 'McRelish Special Fried Rice', 'Special Fried Rice & Egg', 1800, 1, 119),
('m:special-fried-rice:beef', 'McRelish Special Fried Rice', 'Special Fried Rice & Beef', 2000, 1, 120),
('m:special-fried-rice:chicken', 'McRelish Special Fried Rice', 'Special Fried Rice & Chicken', 3000, 1, 121),
('m:special-fried-rice:turkey', 'McRelish Special Fried Rice', 'Special Fried Rice & Turkey', 4000, 1, 122),
('m:special-fried-rice:jumbo-turkey', 'McRelish Special Fried Rice', 'Special Fried Rice & Jumbo Turkey', 5000, 1, 123),
('m:special-fried-rice:no-protein', 'McRelish Special Fried Rice', 'Special Fried Rice only — no protein', 1500, 1, 124),
('e:special-fried-rice:rice', 'McRelish Special Fried Rice', 'Extra Rice', 1000, 1, 125),
('e:special-fried-rice:chicken', 'McRelish Special Fried Rice', 'Extra Chicken', 1500, 1, 126),
('s:special-fried-rice:chicken', 'McRelish Special Fried Rice', 'Extra Chicken — on its own', 2000, 1, 127),
('e:special-fried-rice:beef', 'McRelish Special Fried Rice', 'Extra Beef', 500, 1, 128),
('e:special-fried-rice:egg', 'McRelish Special Fried Rice', 'Extra Egg', 300, 1, 129),
('e:special-fried-rice:turkey', 'McRelish Special Fried Rice', 'Extra Turkey', 2500, 1, 130),
('e:special-fried-rice:jumbo-turkey', 'McRelish Special Fried Rice', 'Extra Jumbo Turkey', 3500, 1, 131),
('e:special-fried-rice:water', 'McRelish Special Fried Rice', 'Water', 300, 1, 132),
('e:special-fried-rice:plantain', 'McRelish Special Fried Rice', 'Plantain', 500, 1, 133),
('e:special-fried-rice:coleslaw', 'McRelish Special Fried Rice', 'Coleslaw', 500, 1, 134),
('s:special-fried-rice:coleslaw', 'McRelish Special Fried Rice', 'Coleslaw — on its own', 1000, 1, 135),
('p:solo-chicken', 'Chicken — Solo Plate', 'Chicken — Solo Plate', 2000, 1, 3),
('p:solo-coleslaw', 'Coleslaw — Solo Plate', 'Coleslaw — Solo Plate', 1000, 1, 4),
('p:family-jollof-pack', 'Family Jollof Pack', 'Family Jollof Pack', 7500, 1, 5),
('p:deluxe-fried-rice', 'Deluxe Fried Rice', 'Deluxe Fried Rice', 7500, 1, 6),
('p:coconut-rice-pack', 'Coconut Rice Pack', 'Coconut Rice Pack', 8000, 1, 7),
('p:classic-box', 'The Classic Box', 'The Classic Box', 4500, 1, 8),
('p:premium-box', 'The Premium Box', 'The Premium Box', 6000, 1, 9),
('p:gourmet-box', 'The Gourmet Box', 'The Gourmet Box', 7500, 1, 10),
('p:celebration-box', 'The Celebration Box', 'The Celebration Box', 9000, 1, 11),
('p:jolly-fest', 'Jolly Fest Feast', 'Jolly Fest Feast', 5500, 1, 1);

INSERT INTO store_settings (id, store_open, note) VALUES (1, 1, '');
INSERT INTO hero_promo (id, label, title, subtitle, product_id, active, image_url) VALUES (1, 'November Jolly Fest', 'Jolly Fest Feast', 'Special Jollof · Peppered Chicken · 2 Peppered Beef · Plantain · Coleslaw · Water · Zobo', 'jolly-fest', 1, '');
