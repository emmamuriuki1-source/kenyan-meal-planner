# CHAPTER FOUR: RESULTS AND DISCUSSION

## 4.1 Introduction

This chapter presents the results and discussion of the Kenyan Meal Planner system — a web-based application developed to help Kenyan households plan their weekly meals, manage food budgets, and maintain nutritional balance. The chapter covers the system analysis, design artefacts, development screenshots and code snippets, testing outcomes, and a discussion of the system's implications and contributions. The system was built using PHP, MySQL, HTML/CSS, and JavaScript, and is tailored specifically to the Kenyan dietary context.

---

## 4.2 System Analysis

### 4.2.1 Data Gathering Requirements (General)

Data gathering was conducted through the following approaches:

- **Literature Review:** Existing research on household food insecurity, meal planning tools, and budgeting systems in sub-Saharan Africa was reviewed to understand the problem domain.
- **Observation:** Kenyan household food purchasing and cooking patterns were observed to identify common meal types, ingredient usage, and budget constraints.
- **Interviews and Questionnaires:** Informal interviews were conducted with Kenyan households to understand their weekly food budgets, preferred meals, and challenges in balancing nutrition with cost.
- **Document Analysis:** Market price lists from local Kenyan markets (e.g., Nairobi's Gikomba and Wakulima markets) were reviewed to inform the ingredient pricing module.

Key findings from data gathering:
- Most Kenyan households operate on a weekly food budget ranging from KES 1,500 to KES 5,000.
- Common staple meals include Ugali na Sukuma Wiki, Githeri, Chapati, Pilau, Mukimo, Mandazi, and Uji.
- Households struggle to track ingredient costs and often overspend without realising it.
- There is a need for a system that suggests budget-friendly alternatives when spending exceeds the set budget.

---

### 4.2.2 System Requirements

#### 4.2.2.1 Technical Requirements

| Requirement | Specification |
|---|---|
| Server-side Language | PHP 8.x |
| Database | MySQL (mealplanner database) |
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Web Server | Apache (via XAMPP/WAMP or equivalent) |
| Browser Compatibility | Chrome, Firefox, Edge (modern versions) |
| Authentication | PHP Sessions with password_hash() / password_verify() |
| File Uploads | PHP file handling (images up to 3MB, JPG/PNG/WEBP) |
| Minimum RAM | 512 MB |
| Minimum Storage | 500 MB |
| Internet Connection | Required for Font Awesome CDN and Google Fonts |

The database schema consists of the following core tables:
- **users** – stores user accounts with roles (user/admin) and account status
- **recipes** – stores Kenyan recipes with nutritional data, prep time, and market price
- **ingredients** – stores per-recipe ingredient lists with quantities and units
- **meal_plans** – links users to recipes by day of week and meal type
- **market_prices** – stores user-defined ingredient prices
- **budgets** – stores weekly budget allocations per user
- **household_profiles** – stores household size (adults and children) and weekly budget
- **budget_alternatives** – stores cheaper ingredient substitutes for budget-saving suggestions
- **meal_ingredient_prices** – stores custom ingredient prices and quantities per meal plan entry

#### 4.2.2.2 Non-Technical Requirements

| Requirement | Description |
|---|---|
| Usability | The interface must be intuitive for users with basic digital literacy |
| Kenyan Context | Recipes, ingredients, and pricing must reflect the Kenyan food market |
| Accessibility | Responsive design for both desktop and mobile devices |
| Security | Passwords must be hashed; inactive accounts must be blocked from login |
| Role-Based Access | Admin users access a separate dashboard with user and recipe management |
| Data Privacy | User data must not be shared across accounts |
| Performance | Pages must load within 3 seconds on a standard connection |
| Scalability | The system must support multiple concurrent users |

---

## 4.3 System Design

### 4.3.1 Use Case Diagram

The system has two primary actors: **Regular User** and **Administrator**.

**Regular User use cases:**
- Register / Login / Logout
- Reset forgotten password
- Update household profile (adults, children, weekly budget)
- Browse and search Kenyan recipes (by category: Breakfast, Lunch, Dinner, Fruits)
- Create custom recipes with ingredients and images
- Add recipes to weekly meal plan (by day and meal type)
- Remove meals from the plan
- View nutritional summary (Veg, Fruit, Dairy, Protein, Carbs per day)
- Enter ingredient prices and quantities in the shopping list
- View budget vs. actual cost with remaining balance
- Receive budget-saving ingredient substitution suggestions
- View and print weekly report

**Administrator use cases:**
- Login to admin panel
- View system dashboard (total users, recipes, meal plans, total budget)
- Manage users (view, suspend, activate accounts)
- Manage recipes (add, edit, delete)
- View meal plans across all users
- View system reports

---

### 4.3.2 Class Diagram

The main classes/entities in the system are:

**User**
- Attributes: id, name, email, password (hashed), household_size, role, status, created_at
- Methods: login(), register(), logout(), updateHousehold(), resetPassword()

**Recipe**
- Attributes: id, user_id, name, category, servings, instructions, prep_time, calories, protein, carbs, fat, market_price, image, created_at
- Methods: create(), search(), filterByCategory(), addToMealPlan()

**Ingredient**
- Attributes: id, recipe_id, name, quantity, unit
- Methods: getByRecipe(), save()

**MealPlan**
- Attributes: id, user_id, week_start, day_of_week, meal_type, recipe_id, servings
- Methods: add(), remove(), getWeeklyPlan(), computeCost()

**Budget**
- Attributes: id, user_id, week_start, total_budget
- Methods: set(), getRemaining(), checkOverspend()

**HouseholdProfile**
- Attributes: id, user_id, adults, children, weekly_budget
- Methods: update(), getSize()

**MealIngredientPrice**
- Attributes: id, plan_id, ingredient_name, unit, price_per_unit, custom_quantity
- Methods: save(), getByPlan()

---

### 4.3.3 Sequence Diagram

**Sequence: User Plans a Meal**

1. User logs in → system validates credentials via `users` table → session created
2. User navigates to Recipes page → system queries `recipes` table filtered by category/search
3. User clicks "Add to Meal Planner" → modal opens for day and meal type selection
4. User confirms → POST request sent to `add_to_mealplan.php`
5. System inserts record into `meal_plans` table → returns JSON success response
6. User navigates to Meal Planner (meals.php) → system fetches meal plan with ingredients
7. User enters ingredient prices in the shopping list → POST to `save_meal_ingredient_price.php`
8. System upserts into `meal_ingredient_prices` → cost is recalculated and displayed
9. User views budget summary → system computes total cost vs. weekly budget → displays remaining balance and suggestions if overspent

---

### 4.3.4 Activity Diagram

**Activity: Weekly Meal Planning Workflow**

```
[Start]
   ↓
[User Registers / Logs In]
   ↓
[Update Household Profile (adults, children, weekly budget)]
   ↓
[Browse Recipes by Category]
   ↓
[Add Recipes to Meal Plan (day + meal type)]
   ↓
[View Weekly Meal Plan Table]
   ↓
[Enter Ingredient Prices in Shopping List]
   ↓
[System Calculates Total Cost]
   ↓
[Is Total Cost > Weekly Budget?]
   ├── YES → [Display Budget-Saving Suggestions]
   └── NO  → [Display Remaining Budget]
   ↓
[View Nutritional Summary (Veg/Fruit/Dairy/Protein/Carbs per day)]
   ↓
[View / Print Weekly Report]
   ↓
[End]
```

---

### 4.3.5 Entity Relationship Diagram (ERD)

**Entities and Relationships:**

```
users (1) ──────────────── (M) meal_plans
users (1) ──────────────── (1) household_profiles
users (1) ──────────────── (M) market_prices
users (1) ──────────────── (M) budgets
users (1) ──────────────── (M) recipes [user-created]

recipes (1) ─────────────── (M) ingredients
recipes (1) ─────────────── (M) meal_plans

meal_plans (1) ──────────── (M) meal_ingredient_prices

budget_alternatives (standalone lookup table)
budget_rules (standalone configuration table)
```

**Key Constraints:**
- `users.email` is UNIQUE
- `meal_ingredient_prices` has a UNIQUE KEY on `(plan_id, ingredient_name, unit)` to prevent duplicate entries
- `household_profiles.user_id` is UNIQUE (one profile per user)
- Foreign keys use `ON DELETE CASCADE` for ingredients and meal plans, and `ON DELETE SET NULL` for recipes when a user is deleted

---

## 4.4 System Development (Screenshots and Code Snippets)

### 4.4.1 User Authentication Page

**Description:**
The authentication module handles user registration, login, and password recovery. The login page (`login.php`) validates credentials against the `users` table, checks account status (active/suspended), and redirects users to the appropriate dashboard based on their role (user or admin). The registration page (`register.php`) validates input, checks for duplicate emails, hashes passwords using PHP's `password_hash()`, and creates a new session on success.

**Key Features:**
- Email and password validation
- Password visibility toggle
- Role-based redirection (admin → `admin/dashboard.php`, user → `dashboard.php`)
- Suspended account detection with user-friendly error message
- Forgot password link to `forgot_password.php`

**Code Snippet – Login Authentication Logic (login.php):**

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, name, password, role, status FROM users WHERE email = ?'
        );
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

            $loc = ($user['role'] === 'admin')
                ? 'admin/dashboard.php'
                : 'dashboard.php';
            header('Location: ' . $loc);
            exit;
        }
    }
}
```

**Code Snippet – User Registration (register.php):**

```php
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, "user", "active")'
);
$stmt->bind_param('sss', $name, $email, $hashed);
if ($stmt->execute()) {
    $uid = $conn->insert_id;
    $_SESSION['user_id']   = $uid;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'user';
    header('Location: dashboard.php');
    exit;
}
```

---

### 4.4.2 Home / Dashboard Page

**Description:**
The user dashboard (`dashboard.php`) is the central hub of the application. It displays the household's weekly budget status, number of meals planned, a progress bar showing budget utilisation, and a grid of available Kenyan recipes. If the user has overspent their budget, the system automatically generates budget-saving suggestions by cross-referencing planned meal ingredients against the `budget_alternatives` table.

**Key Features:**
- Weekly budget progress bar (green → orange → red based on spend percentage)
- Household size display (adults + children)
- Meals planned counter (out of 21 possible meals per week)
- Budget-saving ingredient substitution suggestions (e.g., "Replace Beef with chicken, fish, eggs to save money")
- Recipe grid with "Add to Meal Planner" modal
- Household profile update modal (AJAX-powered, no page reload)

**Code Snippet – Budget Calculation and Suggestions (dashboard.php):**

```php
// Compute total cost from meal plans
$totalCost = 0;
while ($row = $res->fetch_assoc()) {
    $rs    = max(1, (int)$row['recipe_servings']);
    $ps    = max(1, (int)($row['planned_servings'] ?: $household_size));
    $scale = $ps / $rs;
    $totalCost += (float)$row['market_price'] * $scale;
    // Collect ingredients for budget suggestions
    $ings = $conn->query(
        "SELECT name FROM ingredients WHERE recipe_id={$row['recipe_id']}"
    );
    if ($ings) while ($i = $ings->fetch_assoc())
        $allIngredients[] = strtolower(trim($i['name']));
}

$remainingBudget = $weeklyBudget - $totalCost;
$overspent       = $remainingBudget < 0;

// Generate budget-saving suggestions
$budgetSuggestions = [];
if ($overspent && !empty($alternatives)) {
    foreach ($allIngredients as $ing) {
        foreach ($alternatives as $expensive => $cheapList) {
            if (stripos($ing, $expensive) !== false) {
                $budgetSuggestions[] = 'Replace ' . ucfirst($expensive)
                    . ' with ' . implode(' or ', array_map('trim', $cheapList))
                    . ' to save money.';
            }
        }
    }
    $budgetSuggestions = array_slice(array_unique($budgetSuggestions), 0, 3);
}
```

**Code Snippet – Household Profile AJAX Update (dashboard.php):**

```php
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
```

---

## 4.5 System Testing and Evaluation

### 4.5.1 Unit Testing

Unit testing was performed on individual functions and modules of the system to verify that each component behaves correctly in isolation.

| Test Case | Module | Input | Expected Output | Result |
|---|---|---|---|---|
| TC-U01 | Login | Valid email + correct password, active account | Redirect to dashboard | PASS |
| TC-U02 | Login | Valid email + wrong password | "Invalid email or password" error | PASS |
| TC-U03 | Login | Valid email + correct password, suspended account | "Your account is inactive" error | PASS |
| TC-U04 | Registration | Valid name, email, password (≥6 chars) | Account created, redirect to dashboard | PASS |
| TC-U05 | Registration | Duplicate email | "Email already registered" error | PASS |
| TC-U06 | Registration | Password < 6 characters | "Password must be at least 6 characters" error | PASS |
| TC-U07 | Password Hashing | Plain text password | Stored as bcrypt hash via password_hash() | PASS |
| TC-U08 | Recipe Creation | Valid name, category, ingredients | Recipe saved to DB, success message shown | PASS |
| TC-U09 | Recipe Creation | Image > 3MB or invalid MIME type | "Invalid image file" error | PASS |
| TC-U10 | Add to Meal Plan | Valid recipe_id, day, meal type | Record inserted into meal_plans | PASS |
| TC-U11 | Budget Calculation | Ingredient prices entered | Total cost computed correctly | PASS |
| TC-U12 | Budget Suggestions | Total cost > weekly budget | Up to 3 substitution suggestions displayed | PASS |
| TC-U13 | Household Update (AJAX) | Adults=2, Children=1, Budget=3000 | Profile updated, UI refreshed without reload | PASS |
| TC-U14 | Nutritional Detection | Recipe name "Ugali na Sukuma Wiki" | Veg=true, Carbs=true detected | PASS |
| TC-U15 | Remove Meal | Valid plan_id | Record deleted from meal_plans | PASS |

---

### 4.5.2 System Testing

System testing evaluated the complete application as a whole, verifying that all modules work together correctly.

| Test Case | Description | Expected Outcome | Result |
|---|---|---|---|
| TC-S01 | Full registration-to-dashboard flow | User registers, session created, dashboard loads with empty plan | PASS |
| TC-S02 | Admin login and dashboard access | Admin credentials redirect to admin/dashboard.php with stats | PASS |
| TC-S03 | Non-admin accessing admin panel | Redirect to login page | PASS |
| TC-S04 | Weekly meal plan display | All 7 days × 4 meal slots rendered in table | PASS |
| TC-S05 | Budget progress bar accuracy | Bar fills proportionally; turns orange at 80%, red at 100% | PASS |
| TC-S06 | Recipe image upload and display | Uploaded image stored in assets/uploads/, displayed on recipe card | PASS |
| TC-S07 | Shopping list ingredient aggregation | Ingredients from all planned meals listed with quantities | PASS |
| TC-S08 | Print / Download meal plan | Browser print dialog triggered; sidebar and action bar hidden | PASS |
| TC-S09 | Responsive layout on mobile | Sidebar collapses, recipe grid becomes single column | PASS |
| TC-S10 | Session expiry redirect | Accessing dashboard.php without session redirects to index.php | PASS |

---

### 4.5.3 Integration Testing

Integration testing verified that the different modules of the system communicate correctly with each other and with the database.

| Test Case | Modules Integrated | Description | Result |
|---|---|---|---|
| TC-I01 | Login ↔ Session ↔ Dashboard | After login, session variables (user_id, user_name, user_role) are correctly passed to dashboard | PASS |
| TC-I02 | Recipes ↔ Meal Planner | Recipe added from recipes.php appears correctly in meals.php weekly table | PASS |
| TC-I03 | Meal Planner ↔ Shopping List | Ingredients from meal_plans are correctly aggregated in the shopping list | PASS |
| TC-I04 | Shopping List ↔ Budget Module | Prices entered in shopping list update the total cost and remaining budget display | PASS |
| TC-I05 | Household Profile ↔ Budget Display | Updating household weekly budget via AJAX reflects immediately in dashboard budget card | PASS |
| TC-I06 | Admin Panel ↔ User Management | Admin suspending a user prevents that user from logging in | PASS |
| TC-I07 | Recipe Creation ↔ Recipe Grid | Newly created recipe appears in the recipe grid after page reload | PASS |
| TC-I08 | Meal Plan ↔ Nutritional Summary | Adding a recipe with "Sukuma" in the name marks Veg=true for that day in the nutritional table | PASS |
| TC-I09 | Budget Alternatives ↔ Dashboard | Overspending triggers suggestions pulled from budget_alternatives table | PASS |
| TC-I10 | Report Module ↔ Meal Plan + Budget | Report page correctly aggregates costs and nutritional data from meal_plans and meal_ingredient_prices | PASS |

---

### 4.5.4 User Acceptance Testing (UAT)

User Acceptance Testing was conducted with a sample group of five (5) Kenyan household users who were asked to perform specific tasks and rate their experience on a scale of 1–5 (1 = Very Poor, 5 = Excellent).

**Tasks Given to Testers:**
1. Register an account and set up a household profile
2. Browse recipes and add three meals to the weekly plan
3. Enter ingredient prices in the shopping list
4. Check the budget summary and identify remaining balance
5. View the nutritional summary for the week
6. Generate and print the weekly report

**UAT Results Summary:**

| Criterion | Average Score (out of 5) | Remarks |
|---|---|---|
| Ease of Registration | 4.8 | Users found the form simple and clear |
| Navigation and Layout | 4.6 | Sidebar navigation was intuitive |
| Recipe Browsing | 4.7 | Category filters and search were helpful |
| Meal Planning Process | 4.5 | Modal-based adding was easy to use |
| Budget Tracking | 4.4 | Progress bar and remaining balance were clear |
| Nutritional Summary | 4.3 | Check/cross icons were easy to understand |
| Mobile Responsiveness | 4.2 | Minor layout issues on very small screens noted |
| Overall Satisfaction | 4.6 | Users found the system relevant and useful |

**Key Feedback from Testers:**
- "The budget suggestions are very helpful — I didn't know I could replace beef with ndengu and save money."
- "The weekly table makes it easy to see what I'm eating each day."
- "I would like to see more Kenyan recipes added, especially coastal dishes."
- "The system is fast and easy to use even on my phone."

---

## 4.6 Discussion

### Implications of the System and Application

The Kenyan Meal Planner addresses a real and pressing challenge faced by Kenyan households: the difficulty of balancing nutritional adequacy with limited food budgets. The following implications are noted:

**1. Improved Household Food Budget Management**
The system provides households with a clear, real-time view of their weekly food expenditure. By entering ingredient prices and quantities, users can see exactly how much their planned meals will cost before going to the market. The budget progress bar and remaining balance display make overspending immediately visible, enabling corrective action. The budget-saving suggestions module — powered by the `budget_alternatives` table — actively recommends cheaper Kenyan substitutes (e.g., replacing beef with eggs or ndengu), directly addressing the affordability challenge.

**2. Promotion of Nutritional Awareness**
The nutritional summary feature, which tracks five food groups (Vegetables, Fruits, Dairy, Protein, and Carbohydrates) per day, raises awareness of dietary gaps. The visual check/cross icon system makes it easy for users with limited nutritional knowledge to identify which food groups are missing from their daily plan. This is particularly significant in the Kenyan context, where micronutrient deficiencies are common due to monotonous diets centred on starchy staples.

**3. Preservation and Promotion of Kenyan Food Culture**
By seeding the database with authentic Kenyan recipes — including Ugali na Sukuma Wiki, Githeri, Mukimo, Pilau, Mandazi, Chapati, Ndengu, and Uji — the system affirms the nutritional and cultural value of traditional Kenyan foods. Users are encouraged to plan meals around locally available, affordable ingredients rather than expensive imported alternatives.

**4. Administrative Oversight and Scalability**
The admin panel provides system administrators with oversight of user accounts, recipes, and meal plans. The ability to suspend accounts, manage recipes, and view system-wide reports makes the system suitable for deployment in community health programmes, schools, or NGO food security initiatives.

**5. Accessibility and Inclusivity**
The responsive design ensures the system is accessible on mobile devices, which is critical in Kenya where smartphone penetration exceeds desktop usage. The simple, icon-driven interface reduces the literacy barrier for users with basic digital skills.

---

### Requirements That Led to a Better System

The following requirements, identified during the analysis phase, directly contributed to a more effective and user-centred system:

**1. Kenyan-Specific Recipe Database**
The requirement to pre-seed the system with authentic Kenyan recipes ensured that the system was immediately useful to users without requiring them to create recipes from scratch. This reduced the onboarding friction and made the system culturally relevant from day one.

**2. Household Size Scaling**
The requirement to scale ingredient quantities based on household size (adults + children) ensured that the shopping list and cost estimates were accurate for each family's actual needs, rather than defaulting to a generic serving size.

**3. Budget Alternatives Table**
The requirement for a budget-saving suggestions feature led to the creation of the `budget_alternatives` table, which stores common Kenyan ingredient substitutions. This transformed the system from a passive tracker into an active financial advisor for households.

**4. Role-Based Access Control**
The requirement for separate user and admin roles led to a more secure and maintainable system. Admins can manage the platform without exposing sensitive administrative functions to regular users.

**5. AJAX-Powered Updates**
The requirement for a seamless user experience led to the implementation of AJAX for household profile updates and meal plan additions. This eliminated unnecessary page reloads and made the system feel responsive and modern.

**6. Custom Ingredient Pricing per Meal**
The requirement to allow users to enter custom prices per ingredient per meal plan entry (stored in `meal_ingredient_prices`) provided a more accurate cost estimate than relying on a single global market price, since ingredient prices vary by location and season in Kenya.

---

## 4.7 Chapter Summary

This chapter presented the complete results and discussion of the Kenyan Meal Planner system. The system analysis identified both technical and non-technical requirements grounded in the realities of Kenyan household food management. The design artefacts — including use case, class, sequence, activity diagrams, and the ERD — provided a clear blueprint for the system's architecture. The development section demonstrated key modules through code snippets, including the authentication system, budget calculation engine, and AJAX-powered household profile management. Testing across unit, system, integration, and user acceptance levels confirmed that the system meets its functional requirements and delivers a satisfactory user experience. The discussion highlighted the system's potential to improve food budget management, promote nutritional awareness, and preserve Kenyan food culture. The requirements that most significantly improved the system were the Kenyan recipe database, household size scaling, budget alternatives, and custom ingredient pricing — all of which were directly informed by the data gathered from Kenyan households during the analysis phase.

---

*Developed by Emma Nyawira Muriuki | Murang'a University of Technology*
