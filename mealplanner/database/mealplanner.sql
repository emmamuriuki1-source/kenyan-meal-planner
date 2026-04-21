-- Meal Planner & Budgeting System for Kenyan Homes
-- Database: mealplanner

CREATE DATABASE IF NOT EXISTS mealplanner;
USE mealplanner;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    household_size INT DEFAULT 1,
    role ENUM('user','admin') DEFAULT 'user',
    status ENUM('active','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@mealplanner.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

CREATE TABLE recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    servings INT DEFAULT 1,
    instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

CREATE TABLE market_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
    meal_type ENUM('Breakfast','Lunch','Dinner','Snack'),
    recipe_id INT,
    servings INT DEFAULT 4,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL
);

CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,
    total_budget DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE household_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    weekly_budget DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE budget_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waste_factor INT DEFAULT 10
);

CREATE TABLE budget_alternatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expensive_ingredient VARCHAR(100) NOT NULL,
    cheap_alternatives TEXT NOT NULL
);

-- Seed budget rules
INSERT INTO budget_rules (waste_factor) VALUES (10);

-- Seed budget alternatives (common Kenyan substitutes)
INSERT INTO budget_alternatives (expensive_ingredient, cheap_alternatives) VALUES
('beef',    'chicken, fish, eggs'),
('chicken', 'eggs, beans, ndengu'),
('rice',    'ugali, githeri, sweet potatoes'),
('milk',    'soy milk, uji'),
('cooking oil', 'less oil, steaming');

-- Add prep_time, calories, protein, carbs, fat, market_price, image to recipes
ALTER TABLE recipes
    ADD COLUMN prep_time INT DEFAULT 30,
    ADD COLUMN calories DECIMAL(8,2) DEFAULT 0,
    ADD COLUMN protein DECIMAL(8,2) DEFAULT 0,
    ADD COLUMN carbs DECIMAL(8,2) DEFAULT 0,
    ADD COLUMN fat DECIMAL(8,2) DEFAULT 0,
    ADD COLUMN market_price DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN image VARCHAR(255) DEFAULT NULL;

-- Seed: default Kenyan recipes
INSERT INTO recipes (user_id, name, category, servings, instructions) VALUES
(NULL, 'Ugali na Sukuma Wiki', 'Main Course', 4, 'Boil water, add maize flour gradually while stirring until firm. Fry sukuma wiki with onions and tomatoes.'),
(NULL, 'Githeri', 'Main Course', 4, 'Boil maize and beans together until soft. Fry with onions, tomatoes, and spices.'),
(NULL, 'Chapati', 'Bread', 6, 'Mix flour, water, oil and salt. Knead dough, rest 30 mins, roll flat and cook on pan.'),
(NULL, 'Ndengu (Green Grams)', 'Main Course', 4, 'Boil ndengu until soft. Fry with onions, tomatoes, garlic and spices.'),
(NULL, 'Pilau', 'Main Course', 4, 'Fry pilau spices with onions, add meat, then rice and water. Cook until done.'),
(NULL, 'Mandazi', 'Snack', 8, 'Mix flour, sugar, coconut milk, yeast. Knead, rest, cut into triangles and deep fry.'),
(NULL, 'Mukimo', 'Main Course', 4, 'Boil potatoes, peas, maize and pumpkin leaves together. Mash until smooth.'),
(NULL, 'Uji (Porridge)', 'Breakfast', 2, 'Mix millet or sorghum flour with cold water, add to boiling water, stir until thick.');

-- Seed: default ingredients for Ugali na Sukuma Wiki
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(1, 'Maize Flour', 2, 'cups'),
(1, 'Sukuma Wiki', 1, 'bunch'),
(1, 'Onion', 1, 'piece'),
(1, 'Tomato', 2, 'pieces'),
(1, 'Cooking Oil', 2, 'tbsp'),
(1, 'Salt', 1, 'tsp');

INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(2, 'Dry Maize', 1, 'cup'),
(2, 'Beans', 1, 'cup'),
(2, 'Onion', 1, 'piece'),
(2, 'Tomato', 2, 'pieces'),
(2, 'Cooking Oil', 2, 'tbsp');

INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(3, 'Wheat Flour', 3, 'cups'),
(3, 'Cooking Oil', 3, 'tbsp'),
(3, 'Salt', 1, 'tsp'),
(3, 'Water', 1, 'cup');

-- Ndengu (Green Grams) - recipe_id 4
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(4, 'Green Grams (Ndengu)', 2, 'cups'),
(4, 'Onion', 1, 'piece'),
(4, 'Tomato', 2, 'pieces'),
(4, 'Garlic', 2, 'cloves'),
(4, 'Cooking Oil', 2, 'tbsp'),
(4, 'Salt', 1, 'tsp'),
(4, 'Turmeric', 0.5, 'tsp');

-- Pilau - recipe_id 5
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(5, 'Rice', 2, 'cups'),
(5, 'Beef', 500, 'g'),
(5, 'Onion', 2, 'pieces'),
(5, 'Pilau Masala', 2, 'tbsp'),
(5, 'Cooking Oil', 3, 'tbsp'),
(5, 'Salt', 1, 'tsp'),
(5, 'Water', 4, 'cups');

-- Mandazi - recipe_id 6
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(6, 'Wheat Flour', 3, 'cups'),
(6, 'Sugar', 4, 'tbsp'),
(6, 'Coconut Milk', 1, 'cup'),
(6, 'Yeast', 1, 'tsp'),
(6, 'Cooking Oil', 500, 'ml'),
(6, 'Salt', 0.5, 'tsp');

-- Mukimo - recipe_id 7
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(7, 'Potatoes', 500, 'g'),
(7, 'Green Peas', 1, 'cup'),
(7, 'Maize (Corn)', 1, 'cup'),
(7, 'Pumpkin Leaves', 1, 'bunch'),
(7, 'Onion', 1, 'piece'),
(7, 'Butter', 2, 'tbsp'),
(7, 'Salt', 1, 'tsp');

-- Uji (Porridge) - recipe_id 8
INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES
(8, 'Millet Flour', 3, 'tbsp'),
(8, 'Water', 2, 'cups'),
(8, 'Sugar', 2, 'tbsp'),
(8, 'Milk', 0.5, 'cup');
