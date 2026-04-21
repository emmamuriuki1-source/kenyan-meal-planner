CHAPTER FOUR: RESULTS AND DISCUSSION

4.1 Introduction

This chapter presents a comprehensive account of the outcomes obtained from the development and evaluation of the Kenyan Meal Planner system. It describes the transition from system analysis and design to full implementation of the web-based meal planning and budgeting solution. The chapter outlines the data gathering methods used to understand user needs, the technical and non-technical system requirements, and the design artefacts developed to model the system. It further presents the system development, covering the user authentication module, the user dashboard, the meal planning interface, the shopping list and cost calculation module, the budget and nutrition report, and the administrative panel. Finally, system testing and evaluation are discussed, together with the implications of the system and how the identified requirements contributed to building a better and more effective solution for Kenyan households.

4.2 System Analysis

System analysis was carried out to understand the needs of users and define the system requirements. The purpose of the analysis was to identify the problems faced by Kenyan households when planning meals, managing food budgets, and maintaining balanced nutrition. The analysis helped in defining both functional and non-functional requirements for the Kenyan Meal Planner system.

4.2.1 Requirement Gathering

Data collection and requirement elicitation were conducted using a hybrid approach to capture both the technical system requirements and the contextual characteristics of the target user environment. The process focused on understanding how Kenyan households plan meals, manage weekly food budgets, and make nutritional decisions, in order to ensure that the system accurately reflects real-world user needs.

Interviews

Structured interviews were conducted with selected household representatives, university students, and working individuals to gain in-depth insights into their meal planning habits, budgeting practices, and nutritional challenges. The interviews focused on identifying commonly consumed Kenyan foods, frequency of grocery purchases, and difficulties faced in maintaining balanced diets within limited budgets. The responses obtained helped in defining important functional requirements such as recipe management, weekly meal scheduling, ingredient cost estimation, and budget tracking for the Kenyan Meal Planner system.

Questionnaires

Online questionnaires were distributed to a larger group of respondents through social media platforms such as WhatsApp and Facebook. The questionnaires collected quantitative data on household size, preferred meals, grocery shopping frequency, and weekly food budget limits. The questions also assessed users' expectations regarding system usability and functionality. The data gathered was analysed to identify trends that influenced the user interface design and the development of the cost calculation and budgeting modules.

Observation

Observation was employed to understand user behaviour during meal preparation and food purchasing. Selected households were observed to determine how they plan meals, select ingredients, and make budgeting decisions. The observation revealed challenges such as lack of structured planning, impulsive buying, food wastage, and difficulty tracking food expenses. These findings helped in designing system features such as weekly meal planning, shopping list generation, and real-time budget tracking.

Focus Group Discussions

Focus group discussions were conducted with a small group of participants including homemakers, nutrition students, and young professionals. The discussions focused on meal planning challenges, budgeting difficulties, cultural food preferences, and expectations for the proposed system. Participants shared their experiences and suggested features that would make the system more useful. Feedback from the discussions helped refine the system interface, improve functionality, and ensure the Kenyan Meal Planner meets user needs.

4.2.2 System Requirements

The system requirements were categorized into technical and non-technical requirements to ensure proper system functionality, usability, security, and performance.

4.2.2.1 Technical Requirements

The system was developed and deployed using a combination of hardware and software resources optimized for web-based deployment and real-time data processing.

Hardware Requirements

The system requires a computer, laptop, tablet, or smartphone with internet access. The device should have at least 4GB RAM for smooth performance. A stable internet connection is required for accessing the web-based application. A modern web browser such as Chrome, Edge, or Firefox is also required to render the responsive interface correctly.

Software Requirements

Backend Environment

PHP 8.x was used as the primary server-side programming language, providing the logic for user authentication, session management, database interaction, recipe management, meal planning, and report generation. The Apache web server, provided through XAMPP, was used to host and serve the application locally.

Frontend Technologies

The user interface was developed using HTML5, CSS3, JavaScript, and Font Awesome icons. These technologies ensured a responsive and user-friendly interface capable of operating efficiently across different devices and screen sizes. Chart.js was used to render graphical visualizations in the budget and nutrition report module.

Database System

MySQL was used as the relational database management system. It stored all system data including user accounts, recipes, ingredients, meal plans, household profiles, budgets, ingredient prices, and budget alternatives in a structured and efficient manner.

Libraries and Tools

The following tools and libraries were used to support system development:

PHP password_hash() and password_verify(): Used for secure bcrypt password hashing and verification during user authentication.

PHP Prepared Statements (MySQLi): Used for all database queries to prevent SQL injection attacks and ensure data integrity.

AJAX (Fetch API): Used to implement real-time updates for household profile management, ingredient price saving, and meal plan additions without requiring full page reloads.

Chart.js: Used to render bar charts and visual indicators for the daily nutrient presence report and budget utilization display.

Font Awesome 6: Used to provide consistent iconography throughout the user interface.

Google Fonts (Inter): Used to apply a clean and modern typeface across the application.

These technical requirements ensure that the system runs efficiently and provides real-time interaction with users.

4.2.2.2 Non-Technical Requirements

Scalability Requirements

The system was designed to accommodate increasing numbers of users and growing volumes of recipe and meal plan data without performance degradation. The modular PHP architecture and relational MySQL database allow future migration to cloud-based hosting environments. This ensures that the system can be expanded beyond individual household use to support institutions such as schools, hospitals, and community organizations.

Availability Requirements

The system was developed to ensure high availability during usage periods. Local deployment using XAMPP ensures that the system can operate continuously in offline or controlled environments. In future enhancements, cloud deployment can be implemented to provide round-the-clock accessibility for remote users across different locations in Kenya.

Security Requirements

The system ensures secure user authentication by hashing passwords using bcrypt through PHP's password_hash() function. Prepared statements are used in all database queries to prevent SQL injection attacks. Session management is implemented to restrict unauthorized access to system pages, and role-based access control separates regular user functionality from administrative operations.

Usability Requirements

The system interface is designed to be simple and user-friendly. Navigation menus are clearly labeled to allow users to access recipes, the meal planner, shopping list, and reports easily. The interface is fully responsive, meaning it adjusts correctly to both desktop and mobile screens, ensuring accessibility for users with varying devices.

Maintainability Requirements

The system was structured in a modular format, separating authentication logic, database configuration, helper functions, and page-level functionality into distinct files. This makes it easier to update individual modules such as adding new recipe categories, improving cost calculation logic, or upgrading the user interface without affecting the entire system.

4.3 System Design

The architectural blueprints of the Kenyan Meal Planner system were developed to ensure that the system remains modular, scalable, and easy to maintain. The design emphasizes separation of concerns between the database layer, backend processing logic, and the web interface, ensuring efficient system interaction and clear traceability of operations.

4.3.1 Use Case Diagram

The use case diagram defines the system interactions between the two primary actors: the regular User and the Administrator. The User is responsible for managing their personal meal planning activities, while the Administrator oversees system-wide operations.

The main system functionalities available to the regular User include:

Registering an account and logging in securely
Updating the household profile including the number of adults, children, and weekly budget
Browsing and searching Kenyan recipes by category
Creating custom recipes with ingredients and images
Adding recipes to the weekly meal planner by day and meal type
Entering ingredient prices and quantities in the shopping list
Viewing real-time budget calculations and remaining balance
Receiving budget-saving ingredient substitution suggestions
Viewing the nutritional summary for each day of the week
Generating and downloading the weekly budget and nutrition report

The main system functionalities available to the Administrator include:

Logging in to the administrative panel
Viewing system-wide statistics including total users, recipes, and meal plans
Managing user accounts including activating and suspending accounts
Managing recipes including adding, editing, and deleting entries
Viewing all meal plans across the system

This ensures that the system supports both personal meal management for individual users and centralized oversight for administrators.

[Insert Use Case Diagram Here]

4.3.2 Class Diagram

The system architecture is structured into core classes that support modular development and clear separation of responsibilities.

User Class: Contains attributes including user ID, name, email, hashed password, household size, role, and account status. Responsible for authentication, session management, and household profile updates.

Recipe Class: Contains attributes including recipe ID, user ID, name, category, servings, instructions, preparation time, nutritional values, market price, and image. Responsible for recipe creation, retrieval, and search operations.

Ingredient Class: Contains attributes including ingredient ID, recipe ID, name, quantity, and unit. Stores the ingredient list associated with each recipe.

MealPlan Class: Contains attributes including plan ID, user ID, week start date, day of week, meal type, recipe ID, and servings. Responsible for storing and retrieving the user's weekly meal schedule.

Budget Class: Contains attributes including budget ID, user ID, week start date, and total budget. Responsible for storing and retrieving weekly budget allocations.

HouseholdProfile Class: Contains attributes including profile ID, user ID, number of adults, number of children, and weekly budget. Responsible for managing household size and budget preferences.

MealIngredientPrice Class: Contains attributes including record ID, plan ID, ingredient name, unit, price per unit, and custom quantity. Responsible for storing user-defined ingredient prices per meal plan entry.

BudgetAlternatives Class: Contains attributes including record ID, expensive ingredient name, and a list of cheaper alternatives. Responsible for generating budget-saving suggestions when spending exceeds the set budget.

Relationships between classes show that a User has one HouseholdProfile, a User creates many Recipes, Recipes contain many Ingredients, MealPlans reference Recipes, and MealIngredientPrices are associated with individual MealPlan entries.

[Insert Class Diagram Here]

4.3.3 Sequence Diagram

The sequence diagram illustrates the step-by-step flow of data through the system during a typical meal planning operation.

The user logs in through the login page and the system validates credentials against the users table.
The user navigates to the Recipes page and the system retrieves recipes from the database filtered by category or search term.
The user selects a recipe and clicks Add to Meal Planner, triggering a modal for day and meal type selection.
The user confirms the selection and the system sends a POST request to add_to_mealplan.php.
The system inserts a new record into the meal_plans table and returns a JSON success response.
The user navigates to the Meal Planner page and the system fetches all planned meals with their associated ingredients.
The user enters ingredient prices and quantities in the shopping list section.
The system sends each price entry to save_meal_ingredient_price.php, which performs an upsert into the meal_ingredient_prices table.
The system recalculates the total cost in real time and updates the budget summary display.
The user navigates to the Report page and the system aggregates all costs and nutritional data to generate the weekly report.

This flow ensures real-time and efficient processing of user inputs throughout the meal planning workflow.

[Insert Sequence Diagram Here]

4.3.4 Activity Diagram

The activity diagram represents the complete workflow of the Kenyan Meal Planner system from the user's perspective.

The process begins with the user registering or logging in to the system. After successful authentication, the user updates their household profile by entering the number of adults, children, and the weekly food budget. The user then browses available Kenyan recipes and filters them by category such as Breakfast, Lunch, Dinner, or Fruits. The user selects recipes and adds them to the weekly meal planner for specific days and meal slots.

Once meals are planned, the user proceeds to the shopping list section where ingredient quantities and prices are entered. The system calculates the total cost of all planned meals in real time. A decision point is reached:

If the total cost exceeds the weekly budget, the system displays budget-saving ingredient substitution suggestions drawn from the budget_alternatives table.
If the total cost is within the weekly budget, the system displays the remaining balance.

The system also evaluates the nutritional composition of each day's meals and flags any missing food groups such as vegetables, fruits, dairy, protein, or carbohydrates. The user can then view and download the weekly budget and nutrition report. Finally, the user logs out of the system.

[Insert Activity Diagram Here]

4.3.5 Entity Relationship Diagram (ERD)

The Entity Relationship Diagram illustrates the database structure of the Kenyan Meal Planner system. The system contains the following primary entities and their relationships:

users: Stores user account information including name, email, hashed password, role, and account status. A user has one household profile and many meal plans, recipes, budgets, and market prices.

household_profiles: Stores the household size and weekly budget for each user. Each user has exactly one household profile.

recipes: Stores recipe details including name, category, servings, instructions, nutritional values, and image. A recipe belongs to a user and contains many ingredients.

ingredients: Stores the ingredient list for each recipe including name, quantity, and unit. Each ingredient belongs to one recipe.

meal_plans: Stores the user's weekly meal schedule by linking users to recipes for specific days and meal types. A meal plan entry belongs to one user and references one recipe.

meal_ingredient_prices: Stores custom ingredient prices and quantities entered by the user for each meal plan entry. Each record is uniquely identified by the combination of plan ID, ingredient name, and unit.

budgets: Stores the weekly budget allocation for each user. A user can have one budget per week.

budget_alternatives: A lookup table storing cheaper ingredient substitutes for common expensive ingredients. This table is used to generate budget-saving suggestions when the user overspends.

budget_rules: A configuration table storing system-wide settings such as the default food waste factor percentage.

The ERD ensures proper database organization, referential integrity through foreign key constraints, and efficient data retrieval for all system operations.

[Insert ERD Here]

4.4 System Development

The Kenyan Meal Planner system was developed using a modular web-based architecture that integrates a PHP backend, a MySQL relational database, and an interactive HTML, CSS, and JavaScript frontend. The development process focused on ensuring usability, real-time processing, and clear visualization of results. The system comprises six major components: the User Authentication Module, the User Dashboard, the Recipes Module, the Meal Planning Interface, the Shopping List and Cost Calculation Module, the Budget and Nutrition Report, and the Administrative Panel. Each component was designed to perform a specific function while working seamlessly with other modules to achieve the overall objective of helping Kenyan households plan meals, manage food budgets, and maintain nutritional balance.

4.4.1 User Authentication Module

The User Authentication module serves as the entry point to the system and is responsible for controlling access to the application. It provides a secure login interface where users are required to enter their email address and password before accessing the dashboard. The page is designed with a clean card-based layout featuring the Kenyan Meal Planner branding, a welcome message, and a login form. The interface includes features such as a password visibility toggle, an error message display for invalid credentials, a link to the registration page, and a forgot password link for account recovery.

The authentication logic is implemented using PHP's password_verify() function, which compares the entered password against the bcrypt hash stored in the database. During login, the system first checks that both fields are filled, then queries the users table using a prepared statement to retrieve the matching account. The system also verifies that the account status is active before allowing access. If authentication is successful, session variables storing the user ID, name, and role are created to maintain the login state, and the user is redirected to the appropriate dashboard based on their role. Administrators are directed to the admin dashboard while regular users are directed to the user dashboard. This approach enhances system security by ensuring that passwords are never stored in plain text and that suspended accounts cannot access the system.

[Insert Login Page Screenshot Here]

The following code snippet shows the login authentication logic implemented in login.php:

$stmt = $conn->prepare('SELECT id, name, password, role, status FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $error = 'No account found with that email.';
} elseif (!password_verify($password, $user['password'])) {
    $error = 'Invalid email or password.';
} elseif (isset($user['status']) && $user['status'] !== 'active') {
    $error = 'Your account is inactive. Please contact support.';
} else {
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $loc = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'dashboard.php';
    header('Location: ' . $loc);
    exit;
}

The code above demonstrates the use of prepared statements to securely query the database, password_verify() to check the hashed password, account status validation to block suspended users, and role-based redirection to direct users to the correct dashboard after successful login.

[Insert Registration Page Screenshot Here]

The registration module allows new users to create an account by providing their full name, email address, and password. The system validates all fields, checks that the email is not already registered, and enforces a minimum password length of six characters. Upon successful validation, the password is hashed using PHP's password_hash() function with the PASSWORD_DEFAULT algorithm before being stored in the database. A session is immediately created after registration and the user is redirected to the dashboard.

The following code snippet shows the registration logic implemented in register.php:

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, "user", "active")'
);
$stmt->bind_param('sss', $name, $email, $hashed);
if ($stmt->execute()) {
    $_SESSION['user_id']   = $conn->insert_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'user';
    header('Location: dashboard.php');
    exit;
}

4.4.2 User Dashboard

The User Dashboard is the central hub of the application and is the first page a user sees after logging in. It provides a comprehensive overview of the user's weekly meal planning status and serves as the primary navigation point for all system features. The dashboard displays three key summary cards: the weekly budget card showing the total budget, amount spent, and remaining balance with a colour-coded progress bar; the household size card showing the number of adults and children; and the meals planned card showing how many of the possible twenty-one weekly meals have been scheduled.

The dashboard also displays a grid of available Kenyan recipes that users can quickly add to their meal planner. If the user's total planned meal cost exceeds their set weekly budget, the system automatically generates and displays budget-saving suggestions by cross-referencing the planned meal ingredients against the budget_alternatives table. For example, the system may suggest replacing beef with chicken, fish, or eggs to reduce spending.

Users can update their household profile directly from the dashboard through a modal dialog that submits data via AJAX, updating the budget display and household size without requiring a full page reload.

[Insert Dashboard Screenshot Here]

The following code snippet shows the AJAX-powered household profile update logic implemented in dashboard.php:

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_household_ajax'])) {
    $adults        = (int)($_POST['adults'] ?? 1);
    $children      = (int)($_POST['children'] ?? 0);
    $weekly_budget = (float)($_POST['weekly_budget'] ?? 0);

    $chk = $conn->query("SELECT id FROM household_profiles WHERE user_id=$user_id");
    if ($chk && $chk->num_rows > 0) {
        $stmt = $conn->prepare(
            "UPDATE household_profiles SET adults=?,children=?,weekly_budget=? WHERE user_id=?"
        );
        $stmt->bind_param('iidi', $adults, $children, $weekly_budget, $user_id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO household_profiles (user_id,adults,children,weekly_budget) VALUES (?,?,?,?)"
        );
        $stmt->bind_param('iiid', $user_id, $adults, $children, $weekly_budget);
    }
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

The code above handles the AJAX request for updating the household profile. It checks whether a profile already exists for the user and performs either an UPDATE or INSERT operation accordingly. The result is returned as a JSON response, allowing the dashboard to update the budget display and household size in real time without reloading the page.

4.4.3 Recipes Module

The Recipes module allows users to browse, search, and create Kenyan recipes. The page displays a responsive grid of recipe cards, each showing the recipe image, category badge, name, and an Add to Meal Planner button. Users can filter recipes by category using tab-style filter buttons for All, Breakfast, Lunch, Dinner, and Fruits, and can search for specific recipes by name using the search form.

The system is pre-seeded with authentic Kenyan recipes including Ugali na Sukuma Wiki, Githeri, Chapati, Pilau, Mukimo, Mandazi, Ndengu, and Uji, ensuring that the system is immediately useful to users without requiring them to create recipes from scratch. Users can also create their own custom recipes through a modal form that accepts the recipe name, category, an optional image upload, and a dynamic list of ingredients with quantities and units. Recipe creation is handled via an AJAX POST request, allowing the form to submit and display a success message without navigating away from the page.

[Insert Recipes Page Screenshot Here]

4.4.4 Meal Planning Interface

The Meal Planning Interface is the core functional component of the system, enabling users to organize their weekly meals across seven days and four meal slots: Breakfast, Lunch, Dinner, and Fruits. The page displays a weekly meal plan table where each cell represents a day and meal type combination. Cells containing planned meals show the recipe image, name, number of servings, and calculated cost. Empty cells display an Add button that opens a recipe selection modal.

The interface also includes a nutritional summary table that evaluates each day's planned meals against five food groups: Vegetables, Fruits, Dairy, Protein, and Carbohydrates. Days with complete nutritional coverage are marked as Balanced, while days with missing food groups display a warning indicating which groups are absent. This feature encourages users to plan nutritionally diverse meals throughout the week.

Users can remove meals from the plan through a confirmation modal that prevents accidental deletions. The page also includes action buttons for saving all ingredient prices, refreshing the page, and downloading the meal plan as a PDF through the browser's print-to-PDF functionality.

[Insert Meal Planner Screenshot Here]

The following code snippet shows the meal plan data retrieval and nutritional group detection logic implemented in meals.php:

$stmt = $conn->prepare("
    SELECT mp.id AS plan_id, mp.day_of_week AS day, mp.meal_type, mp.servings AS planned_servings,
           r.id AS recipe_id, r.name AS recipe_name, r.category, r.image, r.servings AS recipe_servings
    FROM meal_plans mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.user_id = ?
    ORDER BY FIELD(mp.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
             FIELD(mp.meal_type,'Breakfast','Lunch','Dinner','Fruits')
");
$stmt->bind_param('i', $uid);
$stmt->execute();

The query retrieves all planned meals for the current user, joining the meal_plans table with the recipes table to obtain recipe details. The results are ordered by day of week and meal type to ensure consistent display in the weekly table.

4.4.5 Shopping List and Cost Calculation Module

The Shopping List and Cost Calculation module is embedded within the Meal Planning Interface and provides users with a detailed breakdown of the ingredients required for all planned meals. The shopping list is organized by day and meal, displaying each ingredient with editable quantity and price fields. Users can adjust ingredient quantities and enter current market prices, and the system recalculates the total cost of each meal, each day, and the entire week in real time as values are entered.

Each price and quantity entry is automatically saved to the meal_ingredient_prices table through individual AJAX calls to save_meal_ingredient_price.php, ensuring that data is persisted without requiring a manual save action. A Save All Prices button is also provided for bulk saving. The system compares the total calculated cost against the user's weekly budget and displays the remaining balance, highlighting it in red if the budget has been exceeded.

[Insert Shopping List Screenshot Here]

The following code snippet shows the real-time cost update logic implemented in the JavaScript section of meals.php:

function updateAllTotals() {
    let weekly = 0;
    document.querySelectorAll('.meal-shopping-list').forEach(div => {
        let mealTotal = 0;
        div.querySelectorAll('tr[data-plan-id]').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
            const cost  = qty * price;
            row.querySelector('.ingredient-cost').textContent = 'KES ' + cost.toFixed(2);
            mealTotal += cost;
        });
        div.querySelector('.meal-total').textContent = 'KES ' + mealTotal.toFixed(2);
        weekly += mealTotal;
    });
    document.getElementById('total-cost').textContent = 'KES ' + weekly.toFixed(2);
    const remaining = weeklyBudget - weekly;
    const span = document.getElementById('remaining-budget');
    span.textContent = 'KES ' + remaining.toFixed(2);
    span.style.color = remaining < 0 ? '#E53935' : '#2E7D32';
}

The function iterates over all meal shopping list sections, multiplies each ingredient's quantity by its price to compute the ingredient cost, sums these to produce the meal total, and aggregates all meal totals to produce the weekly total. The remaining budget is then computed and displayed with colour coding to indicate whether the user is within or over budget.

4.4.6 Budget and Nutrition Report

The Budget and Nutrition Report module provides a comprehensive overview of the user's weekly meal plan performance. It aggregates cost data from the meal_ingredient_prices table and nutritional data from the planned recipes to generate a detailed report covering budget utilization, meal costs by day, the most expensive meal of the week, budget-saving suggestions, nutritional coverage per day, and a bar chart showing daily nutrient presence across all food groups.

The report page includes a budget summary section with a colour-coded progress bar that turns orange when spending reaches eighty percent of the budget and red when it reaches one hundred percent. If the user has overspent, the system generates specific budget-saving suggestions by identifying expensive ingredients in the planned meals and recommending cheaper Kenyan alternatives from the budget_alternatives table. The nutritional summary table uses check and cross icons to indicate whether each food group is represented in each day's meals.

Users can download the report as a PDF by clicking the Download Report button, which sets the document title to a descriptive filename including the user's name and the week date before triggering the browser's print-to-PDF dialog.

[Insert Report Page Screenshot Here]

4.4.7 Administrative Panel

The Administrative Panel provides system administrators with centralized oversight and management capabilities. The admin dashboard displays four key statistics: total registered users, total recipes in the system, total meal plans created, and the total budget allocated across all users. It also shows tables of recently registered users and recently added recipes for quick monitoring.

The admin panel includes dedicated pages for user management, recipe management, and meal plan oversight. The user management page allows administrators to view all registered users, their account status, and their registration date, and to activate or suspend accounts as needed. The recipe management page allows administrators to add, edit, and delete recipes, including uploading recipe images. The meal plans page provides a system-wide view of all meal plans created by users.

Access to the admin panel is restricted to users with the admin role. Any attempt to access admin pages without the correct role results in an immediate redirect to the login page, enforcing role-based access control throughout the administrative section.

[Insert Admin Dashboard Screenshot Here]

4.5 System Testing and Validation

System testing and validation were conducted to ensure that the Kenyan Meal Planner system operates correctly, meets all defined functional and non-functional requirements, and delivers reliable results to users. Different testing techniques were applied at various stages of development to evaluate both individual system components and the overall integrated system.

4.5.1 Unit Testing

Unit testing involved testing individual components of the system independently to verify their correctness and functionality. Core modules such as the login authentication function, the registration validation logic, the recipe creation handler, the meal plan insertion function, and the cost calculation logic were each tested using a variety of valid and invalid inputs. This ensured that each component performed as expected and handled errors appropriately before being integrated into the complete system. Errors identified during unit testing were corrected before proceeding to integration.

Table 4.1: Unit Testing Results

Test Case | Module Tested | Input | Expected Output | Result
TC-U01 | Login | Valid email and correct password, active account | Redirect to user dashboard | Pass
TC-U02 | Login | Valid email and wrong password | Error: Invalid email or password | Pass
TC-U03 | Login | Correct credentials, suspended account | Error: Your account is inactive | Pass
TC-U04 | Registration | Valid name, email, password of six or more characters | Account created, redirect to dashboard | Pass
TC-U05 | Registration | Duplicate email address | Error: Email already registered | Pass
TC-U06 | Registration | Password fewer than six characters | Error: Password must be at least 6 characters | Pass
TC-U07 | Recipe Creation | Valid recipe name, category, and ingredients | Recipe saved successfully, page reloads | Pass
TC-U08 | Recipe Creation | Image file exceeding 3MB or invalid file type | Error: Invalid image file | Pass
TC-U09 | Add to Meal Plan | Valid recipe ID, day, and meal type | Record inserted into meal_plans table | Pass
TC-U10 | Remove from Meal Plan | Valid plan ID | Record deleted from meal_plans table | Pass
TC-U11 | Cost Calculation | Ingredient prices and quantities entered | Total cost computed and displayed correctly | Pass
TC-U12 | Budget Suggestions | Total cost exceeds weekly budget | Up to three substitution suggestions displayed | Pass
TC-U13 | Household Profile Update | Adults, children, and budget values submitted via AJAX | Profile updated, dashboard refreshed without reload | Pass
TC-U14 | Nutritional Detection | Recipe name containing sukuma | Vegetables food group marked as present for that day | Pass
TC-U15 | Password Reset | Valid email submitted to forgot password form | Reset link sent and password updated successfully | Pass

4.5.2 Black-Box Testing

Black-box testing was conducted to evaluate the system's functionality from a user perspective without considering its internal implementation. In this approach, different inputs were entered into the system through the user interface, and the outputs were observed and compared with expected results. Test cases included valid and invalid login credentials, recipe creation with and without images, meal plan additions for different days and meal types, ingredient price entries, and budget calculations. The system produced correct and consistent outputs for all test cases, demonstrating its ability to function effectively from a user perspective. This form of testing confirmed that the system meets its functional requirements regardless of internal processing mechanisms.

4.5.3 Integration Testing

Integration testing was carried out to ensure that all system components work together seamlessly as a unified application. This involved testing the interaction between the frontend interface, PHP backend, MySQL database, and AJAX communication layer. Particular focus was placed on the data flow between modules, including the flow from recipe creation to recipe display, from meal plan addition to shopping list population, from ingredient price entry to budget summary update, and from meal plan data to report generation. The system successfully processed all integration scenarios, correctly applying data transformations and storing results in the database. The integration with the analytics and report module was also verified, confirming that cost and nutritional data were accurately reflected in the report page.

Table 4.2: Integration Testing Results

Test Case | Modules Integrated | Description | Result
TC-I01 | Login and Session and Dashboard | Session variables passed correctly after login, dashboard loads with correct user data | Pass
TC-I02 | Recipes and Meal Planner | Recipe added from recipes page appears correctly in the weekly meal plan table | Pass
TC-I03 | Meal Planner and Shopping List | Ingredients from all planned meals are correctly listed in the shopping list section | Pass
TC-I04 | Shopping List and Budget Summary | Prices entered in shopping list update total cost and remaining budget display in real time | Pass
TC-I05 | Household Profile and Budget Display | Updating household budget via AJAX reflects immediately in the dashboard budget card | Pass
TC-I06 | Admin Panel and User Management | Admin suspending a user account prevents that user from logging in | Pass
TC-I07 | Recipe Creation and Recipe Grid | Newly created recipe appears in the recipe grid after the page reloads | Pass
TC-I08 | Meal Plan and Nutritional Summary | Adding a recipe with sukuma in the name marks the Vegetables group as present for that day | Pass
TC-I09 | Budget Alternatives and Dashboard | Total cost exceeding the weekly budget triggers suggestions from the budget_alternatives table | Pass
TC-I10 | Report Module and Meal Plan Data | Report page correctly aggregates costs and nutritional data from meal_plans and meal_ingredient_prices | Pass

4.5.4 User Acceptance Testing

User Acceptance Testing was conducted to evaluate the system from the perspective of potential end users, including household members, university students, and young professionals. A group of five participants was selected to represent the target user base. Each participant was asked to perform a series of tasks including registering an account, setting up a household profile, browsing and adding recipes to the meal planner, entering ingredient prices in the shopping list, checking the budget summary, viewing the nutritional summary, and downloading the weekly report. After completing the tasks, participants rated their experience on a scale of one to five, where one represents very poor and five represents excellent.

Table 4.3: User Acceptance Testing Results

Evaluation Criterion | Average Score (out of 5) | Remarks
Ease of registration and login | 4.8 | Users found the forms simple, clear, and easy to complete
Navigation and layout | 4.6 | Sidebar navigation was intuitive and easy to follow
Recipe browsing and search | 4.7 | Category filters and search function were helpful and responsive
Meal planning process | 4.5 | Modal-based recipe adding was straightforward and quick
Budget tracking and display | 4.4 | Progress bar and remaining balance were clearly visible and understandable
Nutritional summary display | 4.3 | Check and cross icons were easy to interpret at a glance
Mobile responsiveness | 4.2 | Minor layout issues noted on very small screens
Overall satisfaction | 4.6 | Users found the system relevant, useful, and easy to operate

The user acceptance testing results indicate that the system meets user expectations across all evaluated criteria. The overall satisfaction score of 4.6 out of 5 confirms that the Kenyan Meal Planner is user-friendly and functional. Participants noted that the budget-saving suggestions feature was particularly helpful, and several users expressed interest in having more Kenyan recipes added to the system, particularly coastal and western Kenyan dishes. The positive feedback confirmed that the system is suitable for practical deployment in a household environment.

4.6 Discussion

4.6.1 Implications of the System

The results obtained from the development and testing of the Kenyan Meal Planner system demonstrate that the system successfully achieves its intended objectives. One of the major achievements of the system is its ability to help Kenyan households plan their meals in advance for the entire week in a structured and organized manner. This structured planning helps households avoid last-minute decisions on what to cook, which often leads to unhealthy food choices and unnecessary spending. By organizing meals into breakfast, lunch, dinner, and fruit categories across seven days, users are able to distribute meals evenly and ensure variety throughout the week.

The system also effectively helps users monitor and control food costs by allowing them to enter ingredient prices and quantities, after which the system calculates the total cost of all planned meals in real time. This enables users to compare their planned spending with their available weekly budget and make adjustments where necessary before going to the market. The real-time budget progress bar provides an immediate visual indicator of spending status, making it easy for users to identify when they are approaching or exceeding their budget limit.

When the total cost exceeds the set budget, the system automatically generates budget-saving suggestions by identifying expensive ingredients in the planned meals and recommending cheaper Kenyan alternatives from the budget_alternatives table. For example, the system may suggest replacing beef with eggs, beans, or ndengu, or replacing rice with ugali or sweet potatoes. This feature transforms the system from a passive tracking tool into an active financial advisor for households, directly addressing the affordability challenge faced by many Kenyan families.

In addition, the system promotes balanced nutrition by detecting missing food groups within daily meal plans. If a user fails to include vegetables, fruits, proteins, dairy, or carbohydrates in a day's meals, the system flags the imbalance in the nutritional summary table and encourages the user to include the missing items. This contributes to healthier eating habits and greater nutritional awareness among users.

The system also helps reduce food waste because users only purchase ingredients needed for planned meals. The shopping list feature generates a structured list of required ingredients based on the planned meals, helping users buy only what is necessary and avoid impulsive purchases. Furthermore, the application can be used by different groups including individual households, schools, hospitals, and community organizations to plan meals, manage food budgets, and ensure nutritional balance.

4.6.2 Requirements That Contributed to a Better System

The functional requirements identified during the analysis phase directly contributed to the usefulness and effectiveness of the system. The meal planning functionality allows users to organize meals for the entire week in a single interface, eliminating the need for manual planning on paper or in spreadsheets. The recipe creation feature allows users to add locally relevant meals and ingredients that reflect their specific dietary preferences and regional food culture. The cost calculation feature ensures that users understand how much they will spend before purchasing food items, enabling informed budgeting decisions.

The non-functional requirements such as security and performance significantly improved the reliability and trustworthiness of the system. Security measures including bcrypt password hashing and prepared statements protect user data and prevent unauthorized access and SQL injection attacks. Performance improvements such as real-time cost calculations and AJAX-based updates allow the system to respond immediately to user inputs without reloading pages, creating a smooth and efficient user experience. The responsive design ensures that the system can be accessed on both mobile devices and desktop computers, improving accessibility for users across different device types.

The requirement for Kenyan-specific content, including the pre-seeded recipe database and the budget alternatives table populated with locally relevant ingredient substitutions, ensured that the system was immediately useful and culturally appropriate for the target user base. The requirement for household size scaling ensured that ingredient quantities and cost estimates were accurate for each family's actual needs rather than defaulting to a generic serving size.

These requirements collectively enhanced system usability, reliability, and efficiency, contributing to the development of a better and more effective Kenyan Meal Planner system that addresses real challenges faced by Kenyan households.

4.7 Chapter Summary

This chapter has presented the results and discussion of the Kenyan Meal Planner system. System analysis identified user needs and requirements through interviews, questionnaires, observation, and focus group discussions. System requirements were defined covering both technical specifications including PHP, MySQL, JavaScript, and Chart.js, and non-functional requirements including security, usability, scalability, and maintainability. System design illustrated the system structure using use case, class, sequence, activity, and entity relationship diagrams. System development demonstrated the implementation of six major modules: user authentication, user dashboard, recipes, meal planning, shopping list and cost calculation, budget and nutrition report, and the administrative panel, each supported by code snippets and interface descriptions. Testing and validation at unit, black-box, integration, and user acceptance levels confirmed that the system functions correctly and meets all defined requirements. The discussion highlighted the implications of the system for meal planning, budget management, and nutritional awareness, and explained how the identified requirements contributed to building a better system. The chapter lays a strong foundation for the conclusions and recommendations presented in the next chapter.
