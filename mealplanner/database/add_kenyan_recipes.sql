-- ============================================================
-- Fix existing recipe categories
-- ============================================================
UPDATE recipes SET category='Breakfast' WHERE name='Uji (Porridge)' AND user_id IS NULL;
UPDATE recipes SET category='Breakfast' WHERE name='Mandazi' AND user_id IS NULL;
UPDATE recipes SET category='Breakfast' WHERE name='Chapati' AND user_id IS NULL;
UPDATE recipes SET category='Lunch' WHERE name='Ugali na Sukuma Wiki' AND user_id IS NULL;
UPDATE recipes SET category='Lunch' WHERE name='Githeri' AND user_id IS NULL;
UPDATE recipes SET category='Lunch' WHERE name='Mukimo' AND user_id IS NULL;
UPDATE recipes SET category='Lunch' WHERE name='Ndengu (Green Grams)' AND user_id IS NULL;
UPDATE recipes SET category='Dinner' WHERE name='Pilau' AND user_id IS NULL;

-- ============================================================
-- BREAKFAST RECIPES
-- ============================================================
INSERT IGNORE INTO recipes (user_id, name, category, servings, prep_time, instructions) VALUES
(NULL, 'Uji wa Wimbi (Millet Porridge)', 'Breakfast', 2, 15, 'Mix millet flour with cold water to a smooth paste. Bring 2 cups water to boil, add paste while stirring. Cook 10 mins until thick. Sweeten with sugar or honey.'),
(NULL, 'Mahamri', 'Breakfast', 8, 40, 'Mix flour, coconut milk, sugar, cardamom and yeast. Knead well, rest 1 hour. Roll and cut into triangles, deep fry until puffed and golden.'),
(NULL, 'Boiled Sweet Potatoes (Viazi Vitamu)', 'Breakfast', 4, 25, 'Peel and cut sweet potatoes. Boil in salted water until tender, about 20 minutes. Serve with tea or milk.'),
(NULL, 'Boiled Cassava (Muhogo)', 'Breakfast', 4, 30, 'Peel cassava and cut into pieces. Boil in salted water until soft. Serve with tea or coconut sauce.'),
(NULL, 'Kenyan Chai (Spiced Tea)', 'Breakfast', 4, 10, 'Boil water with milk, add tea leaves, ginger, cardamom and sugar. Simmer 5 mins, strain and serve hot.'),
(NULL, 'Arrowroot (Nduma)', 'Breakfast', 4, 25, 'Peel arrowroots and boil in salted water until tender. Serve as is or with tea.'),
(NULL, 'Boiled Maize (Mahindi)', 'Breakfast', 4, 30, 'Boil fresh maize cobs in salted water for 25-30 minutes until tender. Serve hot.'),
(NULL, 'Fried Eggs with Chapati', 'Breakfast', 2, 20, 'Fry eggs with onions and tomatoes. Serve with warm chapati.'),
(NULL, 'Mkate wa Soda (Soda Bread)', 'Breakfast', 6, 45, 'Mix flour, baking soda, salt and buttermilk. Knead lightly, shape into round loaf. Bake at 200C for 35 mins until golden.');

-- ============================================================
-- LUNCH RECIPES
-- ============================================================
INSERT IGNORE INTO recipes (user_id, name, category, servings, prep_time, instructions) VALUES
(NULL, 'Matumbo (Tripe Stew)', 'Lunch', 4, 60, 'Clean and boil tripe until tender. Fry onions, tomatoes and spices. Add tripe and simmer until sauce thickens.'),
(NULL, 'Beef Stew with Ugali', 'Lunch', 4, 50, 'Fry onions and garlic, add beef and brown. Add tomatoes, spices and water. Simmer until tender. Serve with ugali.'),
(NULL, 'Omena (Silver Cyprinid)', 'Lunch', 4, 20, 'Fry onions and tomatoes. Add washed omena, season with salt and spices. Cook until dry. Serve with ugali.'),
(NULL, 'Kunde (Cowpeas)', 'Lunch', 4, 45, 'Boil cowpeas until soft. Fry onions and tomatoes, add cowpeas and coconut milk. Simmer until creamy.'),
(NULL, 'Irio (Mashed Peas and Potatoes)', 'Lunch', 4, 40, 'Boil potatoes and green peas together. Mash with butter and salt. Mix in corn if desired. Serve with stew.'),
(NULL, 'Wali wa Nazi (Coconut Rice)', 'Lunch', 4, 35, 'Wash rice. Cook in coconut milk with salt until absorbed. Fluff and serve with stew or beans.'),
(NULL, 'Beans Stew', 'Lunch', 4, 60, 'Soak beans overnight. Boil until soft. Fry onions and tomatoes, add beans and simmer with spices.'),
(NULL, 'Ugali na Cabbage', 'Lunch', 4, 25, 'Shred cabbage and fry with onions, tomatoes and spices until soft. Serve with ugali.'),
(NULL, 'Maharagwe ya Nazi (Beans in Coconut)', 'Lunch', 4, 50, 'Boil red kidney beans until soft. Fry onions and tomatoes, add coconut milk and beans. Simmer until thick.');

-- ============================================================
-- DINNER RECIPES
-- ============================================================
INSERT IGNORE INTO recipes (user_id, name, category, servings, prep_time, instructions) VALUES
(NULL, 'Ugali na Nyama Choma', 'Dinner', 4, 60, 'Marinate meat with garlic, lemon and spices. Grill over charcoal until cooked. Serve with ugali and kachumbari.'),
(NULL, 'Chicken Stew', 'Dinner', 4, 50, 'Fry onions and garlic, add chicken pieces and brown. Add tomatoes, spices and water. Simmer until tender.'),
(NULL, 'Fish Stew (Samaki wa Kupaka)', 'Dinner', 4, 35, 'Fry fish in oil. Make sauce with onions, tomatoes, coconut milk and spices. Add fish and simmer.'),
(NULL, 'Ugali na Managu (Black Nightshade)', 'Dinner', 4, 30, 'Boil managu leaves briefly. Fry with onions, tomatoes and salt. Serve with ugali.'),
(NULL, 'Ugali na Terere (Amaranth)', 'Dinner', 4, 25, 'Boil terere leaves briefly. Fry with onions and tomatoes. Season with salt. Serve with ugali.'),
(NULL, 'Biryani', 'Dinner', 6, 90, 'Marinate chicken with spices. Fry onions until golden. Layer rice and chicken, cook on low heat until done.'),
(NULL, 'Nyama na Viazi (Meat and Potatoes)', 'Dinner', 4, 50, 'Fry onions and garlic, add meat and brown. Add potatoes, tomatoes and water. Simmer until potatoes are soft.'),
(NULL, 'Kuku wa Kupaka (Coconut Chicken)', 'Dinner', 4, 60, 'Marinate chicken in coconut milk and spices. Grill until cooked, basting with coconut sauce.');

-- ============================================================
-- FRUITS (replacing Snack)
-- ============================================================
INSERT IGNORE INTO recipes (user_id, name, category, servings, prep_time, instructions) VALUES
(NULL, 'Mango', 'Fruits', 2, 5, 'Peel and slice fresh ripe mango. Serve as a healthy snack or dessert. Rich in vitamins A and C.'),
(NULL, 'Banana', 'Fruits', 2, 2, 'Peel fresh banana and serve. A great energy-boosting snack rich in potassium.'),
(NULL, 'Pawpaw (Papaya)', 'Fruits', 4, 5, 'Peel and deseed pawpaw. Cut into cubes and serve. Rich in vitamin C and digestive enzymes.'),
(NULL, 'Watermelon', 'Fruits', 6, 5, 'Cut watermelon into slices or cubes. Serve chilled. Excellent for hydration.'),
(NULL, 'Pineapple', 'Fruits', 4, 10, 'Peel and core pineapple. Cut into rings or chunks. Rich in vitamin C and bromelain.'),
(NULL, 'Avocado', 'Fruits', 2, 5, 'Halve avocado, remove seed. Scoop flesh and serve with a pinch of salt or lemon. Rich in healthy fats.'),
(NULL, 'Passion Fruit', 'Fruits', 2, 5, 'Halve passion fruits and scoop pulp. Serve as is or mix with water and sugar for juice.'),
(NULL, 'Guava', 'Fruits', 2, 5, 'Wash and slice guava. Serve fresh. Rich in vitamin C and dietary fiber.'),
(NULL, 'Orange', 'Fruits', 2, 5, 'Peel and segment oranges. Serve fresh or squeeze for juice. Excellent source of vitamin C.'),
(NULL, 'Tamarind Juice (Ukwaju)', 'Fruits', 4, 10, 'Soak tamarind in warm water, squeeze out pulp. Mix with sugar and water for a refreshing drink.'),
(NULL, 'Mixed Fruit Salad', 'Fruits', 4, 15, 'Dice mango, banana, pawpaw and pineapple. Mix together with a squeeze of lemon and honey.'),
(NULL, 'Sugarcane Juice', 'Fruits', 4, 10, 'Press fresh sugarcane to extract juice. Serve chilled with a squeeze of lemon.');

-- ============================================================
-- INGREDIENTS for new recipes (using recipe names to find IDs)
-- ============================================================

-- Uji wa Wimbi
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Millet Flour', 4, 'tbsp' FROM recipes WHERE name='Uji wa Wimbi (Millet Porridge)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Water', 2, 'cups' FROM recipes WHERE name='Uji wa Wimbi (Millet Porridge)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Sugar', 2, 'tbsp' FROM recipes WHERE name='Uji wa Wimbi (Millet Porridge)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Milk', 0.5, 'cup' FROM recipes WHERE name='Uji wa Wimbi (Millet Porridge)' AND user_id IS NULL LIMIT 1;

-- Mahamri
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Wheat Flour', 3, 'cups' FROM recipes WHERE name='Mahamri' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Coconut Milk', 1, 'cup' FROM recipes WHERE name='Mahamri' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Sugar', 3, 'tbsp' FROM recipes WHERE name='Mahamri' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Yeast', 1, 'tsp' FROM recipes WHERE name='Mahamri' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 500, 'ml' FROM recipes WHERE name='Mahamri' AND user_id IS NULL LIMIT 1;

-- Boiled Sweet Potatoes
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Sweet Potatoes', 500, 'g' FROM recipes WHERE name='Boiled Sweet Potatoes (Viazi Vitamu)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Boiled Sweet Potatoes (Viazi Vitamu)' AND user_id IS NULL LIMIT 1;

-- Kenyan Chai
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Milk', 2, 'cups' FROM recipes WHERE name='Kenyan Chai (Spiced Tea)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Water', 2, 'cups' FROM recipes WHERE name='Kenyan Chai (Spiced Tea)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Tea Leaves', 2, 'tbsp' FROM recipes WHERE name='Kenyan Chai (Spiced Tea)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Sugar', 3, 'tbsp' FROM recipes WHERE name='Kenyan Chai (Spiced Tea)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Ginger', 1, 'piece' FROM recipes WHERE name='Kenyan Chai (Spiced Tea)' AND user_id IS NULL LIMIT 1;

-- Beef Stew with Ugali
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Beef', 500, 'g' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Onion', 2, 'pieces' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Tomato', 3, 'pieces' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Maize Flour', 2, 'cups' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 3, 'tbsp' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Beef Stew with Ugali' AND user_id IS NULL LIMIT 1;

-- Chicken Stew
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Chicken', 1, 'kg' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Onion', 2, 'pieces' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Tomato', 3, 'pieces' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Garlic', 3, 'cloves' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 3, 'tbsp' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Chicken Stew' AND user_id IS NULL LIMIT 1;

-- Fish Stew
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Fish', 500, 'g' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Coconut Milk', 1, 'cup' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Onion', 1, 'piece' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Tomato', 2, 'pieces' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 3, 'tbsp' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Fish Stew (Samaki wa Kupaka)' AND user_id IS NULL LIMIT 1;

-- Biryani
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Rice', 3, 'cups' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Chicken', 1, 'kg' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Onion', 3, 'pieces' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Biryani Spices', 3, 'tbsp' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Yogurt', 1, 'cup' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 4, 'tbsp' FROM recipes WHERE name='Biryani' AND user_id IS NULL LIMIT 1;

-- Mango
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Mango', 2, 'pieces' FROM recipes WHERE name='Mango' AND user_id IS NULL LIMIT 1;

-- Banana
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Banana', 3, 'pieces' FROM recipes WHERE name='Banana' AND user_id IS NULL LIMIT 1;

-- Pawpaw
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Pawpaw', 1, 'piece' FROM recipes WHERE name='Pawpaw (Papaya)' AND user_id IS NULL LIMIT 1;

-- Watermelon
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Watermelon', 1, 'piece' FROM recipes WHERE name='Watermelon' AND user_id IS NULL LIMIT 1;

-- Avocado
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Avocado', 2, 'pieces' FROM recipes WHERE name='Avocado' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Lemon', 0.5, 'piece' FROM recipes WHERE name='Avocado' AND user_id IS NULL LIMIT 1;

-- Mixed Fruit Salad
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Mango', 1, 'piece' FROM recipes WHERE name='Mixed Fruit Salad' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Banana', 2, 'pieces' FROM recipes WHERE name='Mixed Fruit Salad' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Pawpaw', 0.5, 'piece' FROM recipes WHERE name='Mixed Fruit Salad' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Pineapple', 0.25, 'piece' FROM recipes WHERE name='Mixed Fruit Salad' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Honey', 2, 'tbsp' FROM recipes WHERE name='Mixed Fruit Salad' AND user_id IS NULL LIMIT 1;

-- Wali wa Nazi
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Rice', 2, 'cups' FROM recipes WHERE name='Wali wa Nazi (Coconut Rice)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Coconut Milk', 1, 'cup' FROM recipes WHERE name='Wali wa Nazi (Coconut Rice)' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Wali wa Nazi (Coconut Rice)' AND user_id IS NULL LIMIT 1;

-- Beans Stew
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Beans', 2, 'cups' FROM recipes WHERE name='Beans Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Onion', 1, 'piece' FROM recipes WHERE name='Beans Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Tomato', 2, 'pieces' FROM recipes WHERE name='Beans Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Cooking Oil', 2, 'tbsp' FROM recipes WHERE name='Beans Stew' AND user_id IS NULL LIMIT 1;
INSERT IGNORE INTO ingredients (recipe_id, name, quantity, unit)
SELECT id, 'Salt', 1, 'tsp' FROM recipes WHERE name='Beans Stew' AND user_id IS NULL LIMIT 1;
