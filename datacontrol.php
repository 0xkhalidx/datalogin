<?php
session_start();

// Check if user submitted the login form
if (isset($_POST['submit'])) {
    // Get user input from the form
    $host = $_POST['host'] ?? 'localhost';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? ''; // Default to an empty string if no password is provided
    $dbname = $_POST['dbname'];

    // Create connection to MySQL
    $conn = new mysqli($host, $username, $password, $dbname);

    // Check the connection
    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        // Store connection details in the session
        $_SESSION['host'] = $host;
        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['dbname'] = $dbname;

        // Query to show tables
        $sql = "SHOW TABLES";
        $tablesResult = $conn->query($sql); // Now this is correctly set
    }
} elseif (isset($_GET['logout'])) {
    // Logout and clear session
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']); // Redirect to the login form
    exit();
} elseif (isset($_GET['table'])) {
    // Check if session variables are set for the connection
    if (isset($_SESSION['host'], $_SESSION['username'], $_SESSION['dbname'])) {
        // Connect to the database using session variables
        $conn = new mysqli($_SESSION['host'], $_SESSION['username'], $_SESSION['password'] ?? '', $_SESSION['dbname']);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Get table name
        $table = $_GET['table'];

        // Query to fetch all records from the selected table
        $sql = "SELECT * FROM $table";
        $result = $conn->query($sql);

        // Query to fetch column names for the table
        $columnsSql = "SHOW COLUMNS FROM $table";
        $columnsResult = $conn->query($columnsSql);
        $columns = [];
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        // Deleting a row if the delete action is triggered
        if (isset($_POST['delete'])) {
            $id = $_POST['id'];
            $primaryKeyColumn = $_POST['primary_key']; // Primary key column assumed to be passed from the form
            $deleteSql = "DELETE FROM $table WHERE $primaryKeyColumn = ?";
            $stmt = $conn->prepare($deleteSql);
            $stmt->bind_param('s', $id);
            $stmt->execute();
            header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . $table); // Refresh page after deletion
            exit();
        }
    } else {
        // If no session is set, redirect back to login
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
} else {
    // Show available tables if session variables are already set
    if (isset($_SESSION['host'], $_SESSION['username'], $_SESSION['dbname'])) {
        $conn = new mysqli($_SESSION['host'], $_SESSION['username'], $_SESSION['password'] ?? '', $_SESSION['dbname']);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Fetching the list of tables in the database
        $sql = "SHOW TABLES";
        $tablesResult = $conn->query($sql);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Manager Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->

    <style>
/* Root variables for easy theming */
:root {
  --primary-color: #4a90e2;
  --secondary-color: #2c3e50;
  --background-color: #f5f7fa;
  --card-background: #ffffff;
  --table-header-background: #34495e;
  --text-color: #333333;
  --hover-color: #e9ecef;
  --transition-speed: 0.3s;
  --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Global styles */
body {
  font-family: 'Inter', sans-serif;
  background-color: var(--background-color);
  color: var(--text-color);
  line-height: 1.6;
}

.dashboard-wrapper {
  display: flex;
  min-height: 100vh;
}

/* Sidebar styling */
.sidebar {
  background-color: var(--secondary-color);
  color: white;
  width: 250px;
  height: 100vh;
  position: fixed;
  padding-top: 2rem;
  transition: width var(--transition-speed) ease, transform var(--transition-speed) ease;
}

.sidebar .nav-link {
  color: rgba(255, 255, 255, 0.8);
  font-size: 1rem;
  display: flex;
  align-items: center;
  padding: 0.75rem 1.5rem;
  transition: all var(--transition-speed) ease;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
  color: white;
  background-color: rgba(255, 255, 255, 0.1);
  transform: translateX(5px);
}

.sidebar .nav-link i {
  margin-right: 0.75rem;
  font-size: 1.2rem;
}

/* Main content area */
.main-content {
  margin-left: 250px;
  padding: 2rem;
  background-color: var(--background-color);
  width: calc(100% - 250px);
  transition: margin-left var(--transition-speed) ease, width var(--transition-speed) ease;
}

/* Card styling */
.card {
  background-color: var(--card-background);
  border-radius: 0.5rem;
  box-shadow: var(--box-shadow);
  transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
  overflow: hidden;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.card-header {
  background-color: var(--primary-color);
  color: white;
  padding: 1rem;
  font-weight: 600;
}

/* Table styling */
.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.table thead th {
  background-color: var(--table-header-background);
  color: white;
  font-weight: 600;
  text-transform: uppercase;
  padding: 1rem;
  font-size: 0.875rem;
}

.table tbody tr {
  transition: background-color var(--transition-speed) ease;
}

.table tbody tr:hover {
  background-color: var(--hover-color);
}

.table td {
  padding: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

/* Button styling */
.btn-custom {
  background-color: var(--primary-color);
  color: white;
  border: none;
  border-radius: 0.25rem;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all var(--transition-speed) ease;
}

.btn-custom:hover {
  background-color: darken(var(--primary-color), 10%);
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    z-index: 1000;
  }

  .sidebar.active {
    transform: translateX(0);
  }

  .main-content {
    margin-left: 0;
    width: 100%;
  }

  .toggle-sidebar {
    display: block;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 50%;
    width: 3rem;
    height: 3rem;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color var(--transition-speed) ease;
  }

  .toggle-sidebar:hover {
    background-color: darken(var(--primary-color), 10%);
  }
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.card, .table {
  animation: fadeIn 0.5s ease-out;
}
    </style>
</head>
<body>

    <!-- Dashboard Layout -->
    <div class="dashboard-wrapper">
        
        <!-- Sidebar -->
        <nav class="sidebar">
            <h2 class="text-center text-white mb-4">DB Manager</h2>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="nav-link active">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <?php if (isset($_SESSION['dbname'])): ?>
                <li class="nav-item">
                    <a href="?logout=1" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <!-- Check if logged in or not -->
                <?php if (!isset($_SESSION['dbname'])): ?>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card p-4">
                                <h3 class="text-center mb-4">Database Login</h3>
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="host">Host</label>
                                        <input type="text" class="form-control" name="host" id="host" value="localhost" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control" name="username" id="username" value="root" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" name="password" id="password">
                                    </div>
                                    <div class="form-group">
                                        <label for="dbname">Database Name</label>
                                        <input type="text" class="form-control" name="dbname" id="dbname" required>
                                    </div>
                                    <button type="submit" name="submit" class="btn btn-primary btn-block">Login</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php elseif (isset($_GET['table'])): ?>
                    <h1 class="text-center mb-4">Viewing Table: <?php echo htmlspecialchars($table); ?></h1>
                    <div class="card p-4">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <th><?php echo htmlspecialchars($col); ?></th>
                                    <?php endforeach; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <?php foreach ($columns as $col): ?>
                                                <td><?php echo htmlspecialchars($row[$col]); ?></td>
                                            <?php endforeach; ?>
                                            <td>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="id" value="<?php echo $row[$columns[0]]; ?>">
                                                    <input type="hidden" name="primary_key" value="<?php echo $columns[0]; ?>">
                                                    <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?php echo count($columns) + 1; ?>">No data found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <h1 class="text-center mb-4">Tables in Database: <?php echo htmlspecialchars($_SESSION['dbname']); ?></h1>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card p-4">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Table Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($tablesResult && $tablesResult->num_rows > 0): ?>
                                            <?php while ($row = $tablesResult->fetch_row()): ?>
                                                <tr>
                                                    <td>
                                                        <a href="?table=<?php echo htmlspecialchars($row[0]); ?>" class="text-decoration-none text-dark">
                                                            <i class="fas fa-table"></i> <?php echo htmlspecialchars($row[0]); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td>No tables found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <a href="?logout=1" class="btn btn-danger mt-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap and jQuery Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
// Close connection if it's set and open
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
