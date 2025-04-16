<?php
// Database configuration
$host = 'localhost';
$dbname = 'db_name';
$username = 'user_name';
$password = 'user_pass';

// Establish database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Handle actions from the frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'add') {
        // Add new assignment
        $title = $_POST['title'];
        $subject = $_POST['subject'];
        $semester = $_POST['semester'];
        $due_date = $_POST['due_date'] ? $_POST['due_date'] : null;
        $description = $_POST['description'] ? $_POST['description'] : null;
        $completed = $_POST['completed'] === '1' ? 1 : 0;
        $priority = $_POST['priority'] ?? 'medium'; // Default priority to 'medium'

        $stmt = $pdo->prepare("INSERT INTO assignments (title, subject, semester, due_date, description, completed, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $subject, $semester, $due_date, $description, $completed, $priority]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    } elseif ($action === 'toggle') {
        // Toggle completion status
        $id = $_POST['id'];
        $assignment = $pdo->query("SELECT completed FROM assignments WHERE id = $id")->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            echo json_encode(['success' => false]);
            exit;
        }

        $newStatus = !$assignment['completed'];
        $stmt = $pdo->prepare("UPDATE assignments SET completed = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'delete') {
        // Delete assignment
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }
} elseif ($_GET['action'] === 'get') {
    // Get assignments for a specific semester
    $semester = $_GET['semester'];
    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE semester = ? ORDER BY title ASC");
    $stmt->execute([$semester]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($assignments);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CE Assignment Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #7c3aed;
            --accent: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #c7d2fe;
            border-radius: 10px;
        }
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        .slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }
        /* Custom checkbox */
        .custom-checkbox {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            outline: none;
            cursor: pointer;
            position: relative;
        }
        .custom-checkbox:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .custom-checkbox:checked::after {
            content: "✓";
            position: absolute;
            color: white;
            font-size: 12px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            background: var(--primary);
        }
        /* Bottom sheet modal */
        .bottom-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 1rem 1rem 0 0;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 30;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        .bottom-sheet.open {
            transform: translateY(0);
        }
        .bottom-sheet-handle {
            width: 40px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin: 0.75rem auto;
        }
        /* Chip style for subjects */
        .chip {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        /* Overlay for modal */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 20;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 40;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .toast.show {
            opacity: 1;
        }
        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        /* Subject dropdown */
        .subject-dropdown {
            position: relative;
        }
        .subject-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: none;
        }
        .subject-dropdown-menu.show {
            display: block;
        }
        .subject-dropdown-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        .subject-dropdown-item:hover {
            background-color: #f9fafb;
        }
        /* Responsive adjustments */
        @media (min-width: 640px) {
            .bottom-sheet {
                max-width: 28rem;
                left: 50%;
                transform: translate(-50%, 100%);
                border-radius: 0.5rem;
            }
            .fab {
                bottom: 2rem;
                right: 2rem;
            }
        }
        /* Assignment priority indicators */
        .priority-high {
            border-left: 4px solid #ef4444;
        }
        .priority-medium {
            border-left: 4px solid #f59e0b;
        }
        .priority-low {
            border-left: 4px solid #10b981;
        }
        /* Custom date picker */
        .date-picker-wrapper {
            position: relative;
        }
        .date-picker-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: auto;
            height: auto;
            color: transparent;
            background: transparent;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
    <!-- Header -->
    <header class="sticky top-0 z-10 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="bg-indigo-600 p-2 rounded-lg text-white">
                        <i class="fas fa-tasks text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Assignments</h1>
                        <p class="text-xs text-gray-500">Track your progress</p>
                    </div>
                </div>
                <button id="profile-btn" class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                    <i class="fas fa-user text-gray-600"></i>
                </button>
            </div>
        </div>
    </header>
    <!-- Main Content -->
    <main class="container mx-auto px-4 py-4">
        <!-- Semester Selector -->
        <div class="mb-4">
            <h2 class="text-sm font-medium text-gray-500 mb-2">Current Semester</h2>
            <div class="flex overflow-x-auto pb-2 space-x-2" id="semester-tabs">
                <button class="semester-tab px-4 py-2 rounded-full bg-indigo-600 text-white whitespace-nowrap" data-semester="1">
                    Semester 1
                </button>
                <button class="semester-tab px-4 py-2 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap" data-semester="2">
                    Semester 2
                </button>
                <button class="semester-tab px-4 py-2 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap" data-semester="3">
                    Semester 3
                </button>
                <button class="semester-tab px-4 py-2 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap" data-semester="4">
                    Semester 4
                </button>
                <button class="semester-tab px-4 py-2 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap" data-semester="5">
                    Semester 5
                </button>
                <button class="semester-tab px-4 py-2 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap" data-semester="6">
                    Semester 6
                </button>
            </div>
        </div>
        <!-- Progress Card -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-base font-semibold">Your Progress</h2>
                <span class="text-xs text-gray-500">Sem <span id="current-semester">1</span></span>
            </div>
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600">Completed</span>
                    <span id="progress-percentage" class="font-medium">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="bg-indigo-600 h-2 rounded-full" style="width: 0%"></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-indigo-50 p-2 rounded-lg">
                    <div class="text-xs text-gray-500">Total</div>
                    <div id="total-count" class="font-bold text-indigo-700">0</div>
                </div>
                <div class="bg-green-50 p-2 rounded-lg">
                    <div class="text-xs text-gray-500">Done</div>
                    <div id="completed-count" class="font-bold text-green-700">0</div>
                </div>
                <div class="bg-orange-50 p-2 rounded-lg">
                    <div class="text-xs text-gray-500">Pending</div>
                    <div id="pending-count" class="font-bold text-orange-700">0</div>
                </div>
            </div>
        </div>
        <!-- Subject Filter -->
        <div class="mb-4">
            <h2 class="text-sm font-medium text-gray-500 mb-2">Filter by Subject</h2>
            <div class="flex overflow-x-auto pb-2 space-x-2" id="subject-tabs">
                <button class="subject-tab px-3 py-1 rounded-full bg-indigo-600 text-white whitespace-nowrap" data-subject="all">
                    All
                </button>
            </div>
        </div>
        <!-- Assignments List -->
        <div>
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-base font-semibold" id="current-subject">All Assignments</h2>
                <button id="sort-btn" class="text-xs text-indigo-600 flex items-center">
                    <i class="fas fa-sort mr-1"></i> Sort
                </button>
            </div>
            <div id="assignments-container">
                <div class="empty-state">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-tasks text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-gray-700 font-medium mb-1">No assignments yet</h3>
                    <p class="text-gray-500 text-sm">Add your first assignment to get started</p>
                </div>
            </div>
        </div>
    </main>
    <!-- Floating Action Button -->
    <button id="add-btn" class="fab bg-indigo-600 text-white">
        <i class="fas fa-plus text-xl"></i>
    </button>
    <!-- Bottom Sheet Modal -->
    <div class="overlay" id="modal-overlay"></div>
    <div class="bottom-sheet" id="assignment-modal">
        <div class="bottom-sheet-handle"></div>
        <div class="px-4 pb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">New Assignment</h3>
                <button id="close-modal" class="text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="assignment-form" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title*</label>
                    <input type="text" id="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-red-500 mt-1 hidden" id="title-error">Please enter a title</p>
                </div>
                <div class="subject-dropdown">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject*</label>
                    <div class="relative">
                        <input type="text" id="subject" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Type or select a subject">
                        <button type="button" id="subject-dropdown-toggle" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="subject-dropdown-menu" class="subject-dropdown-menu mt-1">
                        <!-- Subjects will be populated here -->
                    </div>
                    <p class="text-xs text-red-500 mt-1 hidden" id="subject-error">Please enter a subject</p>
                </div>
                <div>
                    <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester*</label>
                    <select id="semester" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                        <option value="3">Semester 3</option>
                        <option value="4">Semester 4</option>
                        <option value="5">Semester 5</option>
                        <option value="6">Semester 6</option>
                    </select>
                </div>
                <div class="date-picker-wrapper">
                    <label for="due-date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <div class="relative">
                        <input type="date" id="due-date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                            <i class="fas fa-calendar text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <div class="flex space-x-2">
                        <button type="button" data-priority="low" class="priority-btn flex-1 py-1 px-3 border border-gray-300 rounded-lg text-xs flex items-center justify-center">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-1"></span> Low
                        </button>
                        <button type="button" data-priority="medium" class="priority-btn flex-1 py-1 px-3 border border-gray-300 rounded-lg text-xs flex items-center justify-center">
                            <span class="w-2 h-2 rounded-full bg-yellow-500 mr-1"></span> Medium
                        </button>
                        <button type="button" data-priority="high" class="priority-btn flex-1 py-1 px-3 border border-gray-300 rounded-lg text-xs flex items-center justify-center bg-red-50 border-red-200">
                            <span class="w-2 h-2 rounded-full bg-red-500 mr-1"></span> High
                        </button>
                    </div>
                    <input type="hidden" id="priority" value="high">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="completed" class="custom-checkbox">
                    <label for="completed" class="ml-2 text-sm text-gray-700">Mark as completed</label>
                </div>
                <div class="flex space-x-3 pt-2">
                    <button type="button" id="cancel-btn" class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 font-medium">Cancel</button>
                    <button type="submit" class="flex-1 py-2 px-4 bg-indigo-600 text-white rounded-lg font-medium">Save</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Sort Options Bottom Sheet -->
    <div class="bottom-sheet" id="sort-modal">
        <div class="bottom-sheet-handle"></div>
        <div class="px-4 pb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Sort By</h3>
                <button id="close-sort-modal" class="text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 border-b border-gray-100">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt text-gray-500 mr-3"></i>
                        <span>Due Date</span>
                    </div>
                    <input type="radio" name="sort" value="due_date" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-between p-3 border-b border-gray-100">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-gray-500 mr-3"></i>
                        <span>Completion Status</span>
                    </div>
                    <input type="radio" name="sort" value="completed" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-between p-3 border-b border-gray-100">
                    <div class="flex items-center">
                        <i class="fas fa-book text-gray-500 mr-3"></i>
                        <span>Subject</span>
                    </div>
                    <input type="radio" name="sort" value="subject" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center">
                        <i class="fas fa-sort-alpha-down text-gray-500 mr-3"></i>
                        <span>Title</span>
                    </div>
                    <input type="radio" name="sort" value="title" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                </div>
            </div>
            <button id="apply-sort" class="w-full mt-6 py-2 px-4 bg-indigo-600 text-white rounded-lg font-medium">Apply</button>
        </div>
    </div>
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toast-message">Assignment added successfully!</span>
    </div>
    <script>
        // App state
        const state = {
            semester: 1,
            subject: 'all',
            sortBy: 'title',
            assignments: [],
            subjects: {
                1: [],
                2: [],
                3: [],
                4: [],
                5: [],
                6: []
            }
        };
        // DOM elements
        const elements = {
            semesterTabs: document.getElementById('semester-tabs'),
            currentSemester: document.getElementById('current-semester'),
            progressPercentage: document.getElementById('progress-percentage'),
            progressBar: document.getElementById('progress-bar'),
            totalCount: document.getElementById('total-count'),
            completedCount: document.getElementById('completed-count'),
            pendingCount: document.getElementById('pending-count'),
            subjectTabs: document.getElementById('subject-tabs'),
            currentSubject: document.getElementById('current-subject'),
            assignmentsContainer: document.getElementById('assignments-container'),
            modalOverlay: document.getElementById('modal-overlay'),
            assignmentModal: document.getElementById('assignment-modal'),
            assignmentForm: document.getElementById('assignment-form'),
            closeModal: document.getElementById('close-modal'),
            cancelBtn: document.getElementById('cancel-btn'),
            sortBtn: document.getElementById('sort-btn'),
            sortModal: document.getElementById('sort-modal'),
            closeSortModal: document.getElementById('close-sort-modal'),
            applySort: document.getElementById('apply-sort'),
            titleInput: document.getElementById('title'),
            subjectInput: document.getElementById('subject'),
            semesterSelect: document.getElementById('semester'),
            dueDateInput: document.getElementById('due-date'),
            descriptionInput: document.getElementById('description'),
            completedCheckbox: document.getElementById('completed'),
            titleError: document.getElementById('title-error'),
            subjectError: document.getElementById('subject-error'),
            toast: document.getElementById('toast'),
            toastMessage: document.getElementById('toast-message'),
            addBtn: document.getElementById('add-btn'),
            subjectDropdownToggle: document.getElementById('subject-dropdown-toggle'),
            subjectDropdownMenu: document.getElementById('subject-dropdown-menu'),
            priorityInput: document.getElementById('priority'),
            priorityBtns: document.querySelectorAll('.priority-btn')
        };

        // Initialize the app
        function init() {
            // Set default due date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            elements.dueDateInput.valueAsDate = tomorrow;
            // Load assignments from database
            loadAssignments();
            // Set up event listeners
            setupEventListeners();
        }

        // Load assignments from server
        async function loadAssignments() {
            try {
                const response = await fetch('index.php?action=get&semester=' + state.semester);
                if (!response.ok) throw new Error('Failed to load assignments');
                const data = await response.json();
                state.assignments = data;
                updateSubjectsFromAssignments();
                renderAssignments();
                updateProgress();
            } catch (error) {
                console.error('Error loading assignments:', error);
                showToast('Failed to load assignments');
            }
        }

        // Update subjects list from assignments
        function updateSubjectsFromAssignments() {
            // Reset subjects
            for (let i = 1; i <= 6; i++) {
                state.subjects[i] = [];
            }
            // Extract unique subjects per semester
            state.assignments.forEach(assignment => {
                const semester = assignment.semester;
                const subject = assignment.subject;
                if (semester >= 1 && semester <= 6 && !state.subjects[semester].includes(subject)) {
                    state.subjects[semester].push(subject);
                }
            });
            renderSubjectTabs();
            renderSubjectDropdown();
        }

        // Render subject dropdown
        function renderSubjectDropdown() {
            elements.subjectDropdownMenu.innerHTML = '';
            // Add current semester subjects
            state.subjects[state.semester].forEach(subject => {
                const item = document.createElement('div');
                item.className = 'subject-dropdown-item';
                item.textContent = subject;
                item.addEventListener('click', () => {
                    elements.subjectInput.value = subject;
                    elements.subjectDropdownMenu.classList.remove('show');
                });
                elements.subjectDropdownMenu.appendChild(item);
            });
            // Add "Add new" option
            const newItem = document.createElement('div');
            newItem.className = 'subject-dropdown-item text-indigo-600 font-medium';
            newItem.innerHTML = '<i class="fas fa-plus mr-2"></i> Add new subject';
            newItem.addEventListener('click', () => {
                elements.subjectDropdownMenu.classList.remove('show');
                elements.subjectInput.focus();
            });
            elements.subjectDropdownMenu.appendChild(newItem);
        }

        // Render semester tabs
        function renderSemesterTabs() {
            const tabs = elements.semesterTabs.querySelectorAll('.semester-tab');
            tabs.forEach(tab => {
                const semester = parseInt(tab.dataset.semester);
                tab.classList.remove('bg-indigo-600', 'text-white');
                tab.classList.add('bg-gray-200', 'text-gray-700');
                if (semester === state.semester) {
                    tab.classList.remove('bg-gray-200', 'text-gray-700');
                    tab.classList.add('bg-indigo-600', 'text-white');
                }
            });
        }

        // Render subject tabs
        function renderSubjectTabs() {
            // Clear existing tabs (except "All")
            while (elements.subjectTabs.children.length > 1) {
                elements.subjectTabs.removeChild(elements.subjectTabs.lastChild);
            }
            // Add subjects for current semester
            state.subjects[state.semester].forEach(subject => {
                const tab = document.createElement('button');
                tab.className = 'subject-tab px-3 py-1 rounded-full bg-gray-200 text-gray-700 whitespace-nowrap';
                tab.dataset.subject = subject.toLowerCase();
                tab.textContent = subject;
                if (state.subject === subject.toLowerCase()) {
                    tab.classList.remove('bg-gray-200', 'text-gray-700');
                    tab.classList.add('bg-indigo-600', 'text-white');
                }
                elements.subjectTabs.appendChild(tab);
            });
            // Update "All" tab styling
            const allTab = elements.subjectTabs.querySelector('[data-subject="all"]');
            if (state.subject === 'all') {
                allTab.classList.remove('bg-gray-200', 'text-gray-700');
                allTab.classList.add('bg-indigo-600', 'text-white');
            } else {
                allTab.classList.remove('bg-indigo-600', 'text-white');
                allTab.classList.add('bg-gray-200', 'text-gray-700');
            }
            // Update current subject heading
            if (state.subject === 'all') {
                elements.currentSubject.textContent = 'All Assignments';
            } else {
                const subject = state.subjects[state.semester].find(s => 
                    s.toLowerCase() === state.subject
                );
                elements.currentSubject.textContent = `${subject} Assignments`;
            }
        }

        // Render assignments
        function renderAssignments() {
            // Filter assignments by current semester and subject
            let filtered = state.assignments.filter(a => a.semester === state.semester);
            if (state.subject !== 'all') {
                filtered = filtered.filter(a => 
                    a.subject.toLowerCase() === state.subject
                );
            }
            // Sort assignments
            filtered = sortAssignments(filtered);
            // Clear container
            elements.assignmentsContainer.innerHTML = '';
            // Show empty state if no assignments
            if (filtered.length === 0) {
                elements.assignmentsContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-tasks text-indigo-600 text-2xl"></i>
                        </div>
                        <h3 class="text-gray-700 font-medium mb-1">No assignments found</h3>
                        <p class="text-gray-500 text-sm">Try changing filters or add a new assignment</p>
                    </div>
                `;
                return;
            }
            // Create assignment cards
            filtered.forEach((assignment, index) => {
                const dueDate = assignment.due_date ? 
                    new Date(assignment.due_date).toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric' 
                    }) : 
                    'No due date';
                const isOverdue = assignment.due_date && 
                                  !assignment.completed && 
                                  new Date(assignment.due_date) < new Date();
                // Determine priority class
                let priorityClass = '';
                if (assignment.priority === 'high') {
                    priorityClass = 'priority-high';
                } else if (assignment.priority === 'medium') {
                    priorityClass = 'priority-medium';
                } else if (assignment.priority === 'low') {
                    priorityClass = 'priority-low';
                }
                const card = document.createElement('div');
                card.className = `bg-white rounded-xl shadow-sm p-4 mb-3 fade-in ${priorityClass}`;
                card.style.animationDelay = `${index * 0.05}s`;
                card.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <input type="checkbox" 
                                    class="custom-checkbox toggle-completion mr-3" 
                                    data-id="${assignment.id}"
                                   ${assignment.completed ? 'checked' : ''}>
                            <h3 class="font-medium ${assignment.completed ? 'line-through text-gray-400' : 'text-gray-800'}">
                                ${assignment.title}
                            </h3>
                        </div>
                        <button class="delete-assignment text-gray-400 hover:text-red-500" data-id="${assignment.id}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <div class="flex items-center text-xs text-gray-500 mb-2">
                        <span class="chip bg-indigo-100 text-indigo-800 mr-2">
                            ${assignment.subject}
                        </span>
                        <span>•</span>
                        <span class="ml-2">Due ${dueDate}</span>
                        ${isOverdue ? '<span class="ml-2 text-red-500">(Overdue)</span>' : ''}
                    </div>
                    ${assignment.description ? `
                        <p class="text-sm text-gray-600 mb-2 line-clamp-2">
                            ${assignment.description}
                        </p>
                    ` : ''}
                    ${assignment.priority ? `
                        <div class="flex items-center text-xs">
                            <span class="text-gray-500">Priority:</span>
                            <span class="ml-2 font-medium ${
                                assignment.priority === 'high' ? 'text-red-500' : 
                                assignment.priority === 'medium' ? 'text-yellow-500' : 
                                'text-green-500'
                            }">
                                ${assignment.priority.charAt(0).toUpperCase() + assignment.priority.slice(1)}
                            </span>
                        </div>
                    ` : ''}
                `;
                elements.assignmentsContainer.appendChild(card);
            });
                    // Add event listeners to checkboxes and delete buttons
        document.querySelectorAll('.toggle-completion').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                toggleAssignmentCompletion(parseInt(checkbox.dataset.id));
            });
        });
        document.querySelectorAll('.delete-assignment').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteAssignment(parseInt(btn.dataset.id));
            });
        });
    }

    // Sort assignments
    function sortAssignments(assignments) {
        switch (state.sortBy) {
            case 'due_date':
                return [...assignments].sort((a, b) => {
                    const aDate = a.due_date ? new Date(a.due_date) : new Date(8640000000000000);
                    const bDate = b.due_date ? new Date(b.due_date) : new Date(8640000000000000);
                    return aDate - bDate;
                });
            case 'completed':
                return [...assignments].sort((a, b) => {
                    if (a.completed && !b.completed) return 1;
                    if (!a.completed && b.completed) return -1;
                    return 0;
                });
            case 'subject':
                return [...assignments].sort((a, b) => 
                    a.subject.localeCompare(b.subject)
                );
            case 'title':
            default:
                return [...assignments].sort((a, b) => 
                    a.title.localeCompare(b.title)
                );
        }
    }

    // Update progress stats
    function updateProgress() {
        const filtered = state.assignments.filter(a => a.semester === state.semester);
        const total = filtered.length;
        const completed = filtered.filter(a => a.completed).length;
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        elements.totalCount.textContent = total;
        elements.completedCount.textContent = completed;
        elements.pendingCount.textContent = total - completed;
        elements.progressPercentage.textContent = `${percentage}%`;
        elements.progressBar.style.width = `${percentage}%`;
        // Update progress bar color
        if (percentage >= 75) {
            elements.progressBar.className = 'bg-green-500 h-2 rounded-full';
        } else if (percentage >= 50) {
            elements.progressBar.className = 'bg-yellow-500 h-2 rounded-full';
        } else if (percentage > 0) {
            elements.progressBar.className = 'bg-orange-500 h-2 rounded-full';
        } else {
            elements.progressBar.className = 'bg-indigo-600 h-2 rounded-full';
        }
    }

    // Toggle assignment completion
    async function toggleAssignmentCompletion(id) {
        try {
            const formData = new FormData();
            formData.append('action', 'toggle');
            formData.append('id', id);
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                const assignment = state.assignments.find(a => a.id === id);
                if (assignment) {
                    assignment.completed = !assignment.completed;
                    renderAssignments();
                    updateProgress();
                    showToast('Assignment updated');
                }
            } else {
                throw new Error('Toggle failed');
            }
        } catch (error) {
            console.error('Error toggling assignment:', error);
            showToast('Error updating assignment');
        }
    }

    // Delete assignment
    async function deleteAssignment(id) {
        if (confirm('Are you sure you want to delete this assignment?')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    state.assignments = state.assignments.filter(a => a.id !== id);
                    updateSubjectsFromAssignments();
                    renderAssignments();
                    updateProgress();
                    showToast('Assignment deleted successfully!');
                } else {
                    throw new Error('Delete failed');
                }
            } catch (error) {
                console.error('Error deleting assignment:', error);
                showToast('Failed to delete assignment');
            }
        }
    }

    // Add new assignment
    async function addAssignment(assignment) {
        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('title', assignment.title);
            formData.append('subject', assignment.subject);
            formData.append('semester', assignment.semester);
            formData.append('due_date', assignment.due_date || '');
            formData.append('description', assignment.description || '');
            formData.append('completed', assignment.completed ? '1' : '0');
            formData.append('priority', assignment.priority || 'medium'); // Added priority handling
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                assignment.id = data.id;
                state.assignments.push(assignment);
                updateSubjectsFromAssignments();
                renderAssignments();
                updateProgress();
                showToast('Assignment added successfully!');
            } else {
                throw new Error('Add failed');
            }
        } catch (error) {
            console.error('Error adding assignment:', error);
            showToast('Failed to add assignment');
        }
    }

    // Show toast notification
    function showToast(message) {
        elements.toastMessage.textContent = message;
        elements.toast.classList.add('show');
        setTimeout(() => {
            elements.toast.classList.remove('show');
        }, 3000);
    }

    // Open modal
    function openModal() {
        elements.semesterSelect.value = state.semester;
        elements.modalOverlay.classList.add('active');
        elements.assignmentModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    // Close modal
    function closeModal() {
        elements.modalOverlay.classList.remove('active');
        elements.assignmentModal.classList.remove('open');
        document.body.style.overflow = 'auto';
        elements.assignmentForm.reset();
        // Reset due date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        elements.dueDateInput.valueAsDate = tomorrow;
        // Reset priority to high
        setPriority('high');
    }

    // Open sort modal
    function openSortModal() {
        elements.modalOverlay.classList.add('active');
        elements.sortModal.classList.add('open');
        document.body.style.overflow = 'hidden';
        // Set current sort option
        document.querySelector(`input[name="sort"][value="${state.sortBy}"]`).checked = true;
    }

    // Close sort modal
    function closeSortModal() {
        elements.modalOverlay.classList.remove('active');
        elements.sortModal.classList.remove('open');
        document.body.style.overflow = 'auto';
    }

    // Apply sort
    function applySort() {
        const selectedSort = document.querySelector('input[name="sort"]:checked').value;
        state.sortBy = selectedSort;
        renderAssignments();
        closeSortModal();
    }

    // Set priority
    function setPriority(priority) {
        elements.priorityInput.value = priority;
        // Update button styles
        elements.priorityBtns.forEach(btn => {
            btn.classList.remove('bg-red-50', 'border-red-200');
            btn.classList.remove('bg-yellow-50', 'border-yellow-200');
            btn.classList.remove('bg-green-50', 'border-green-200');
            btn.classList.add('border-gray-300');
        });
        const activeBtn = document.querySelector(`.priority-btn[data-priority="${priority}"]`);
        if (priority === 'high') {
            activeBtn.classList.add('bg-red-50', 'border-red-200');
        } else if (priority === 'medium') {
            activeBtn.classList.add('bg-yellow-50', 'border-yellow-200');
        } else if (priority === 'low') {
            activeBtn.classList.add('bg-green-50', 'border-green-200');
        }
    }

    // Set up event listeners
    function setupEventListeners() {
        // Semester tab click
        elements.semesterTabs.addEventListener('click', (e) => {
            const tab = e.target.closest('.semester-tab');
            if (tab) {
                state.semester = parseInt(tab.dataset.semester);
                state.subject = 'all'; // Reset subject filter when switching semesters
                elements.currentSemester.textContent = state.semester;
                renderSemesterTabs();
                renderSubjectTabs();
                loadAssignments(); // Reload assignments for the new semester
            }
        });

        // Subject tab click
        elements.subjectTabs.addEventListener('click', (e) => {
            const tab = e.target.closest('.subject-tab');
            if (tab) {
                state.subject = tab.dataset.subject;
                renderSubjectTabs();
                renderAssignments();
            }
        });

        // Add button click
        elements.addBtn.addEventListener('click', openModal);

        // Close modal buttons
        elements.closeModal.addEventListener('click', closeModal);
        elements.cancelBtn.addEventListener('click', closeModal);
        elements.modalOverlay.addEventListener('click', closeModal);

        // Sort button click
        elements.sortBtn.addEventListener('click', openSortModal);

        // Close sort modal buttons
        elements.closeSortModal.addEventListener('click', closeSortModal);

        // Apply sort button
        elements.applySort.addEventListener('click', applySort);

        // Priority button clicks
        elements.priorityBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const priority = btn.dataset.priority;
                setPriority(priority);
            });
        });

        // Form submission
        elements.assignmentForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // Validate form
            let isValid = true;
            if (!elements.titleInput.value.trim()) {
                elements.titleError.classList.remove('hidden');
                isValid = false;
            } else {
                elements.titleError.classList.add('hidden');
            }
            if (!elements.subjectInput.value.trim()) {
                elements.subjectError.classList.remove('hidden');
                isValid = false;
            } else {
                elements.subjectError.classList.add('hidden');
            }
            if (!isValid) return;

            // Create assignment object
            const assignment = {
                title: elements.titleInput.value.trim(),
                subject: elements.subjectInput.value.trim(),
                semester: parseInt(elements.semesterSelect.value),
                due_date: elements.dueDateInput.value || null,
                description: elements.descriptionInput.value.trim(),
                completed: elements.completedCheckbox.checked ? 1 : 0,
                priority: elements.priorityInput.value || 'medium' // Added priority handling
            };

            // Add assignment
            addAssignment(assignment);
            closeModal();
        });
    }

    // Initialize the app
    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
