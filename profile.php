$doctor = [
    'name' => 'Dr. Ahmed AlAli',
    'specialty' => 'Family Medicine',
    'email' => 'ahmed@example.com',
    'phone' => '+966 5XXXXXXX',
    'profile_pic' => '' // path to image if available
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 600px;
            margin: 40px auto;
            background-color: #1c1c1c;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 0 10px #00bfa5;
        }
        h2 {
            text-align: center;
            color: #00bfa5;
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #333;
            margin: 0 auto 20px auto;
            overflow: hidden;
        }
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-form label {
            display: block;
            margin: 10px 0 5px 0;
        }
        .profile-form input {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: none;
            background-color: #2c2c2c;
            color: #fff;
        }
        .buttons {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            background-color: #00bfa5;
            color: #fff;
            cursor: pointer;
        }
        .buttons button:hover {
            background-color: #00a58c;
        }
        input[type="file"] {
            display: block;
            margin: 10px auto;
            color: #fff;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Profile</h2>

    <div class="profile-pic">
        <?php if($doctor['profile_pic']): ?>
            <img src="<?php echo $doctor['profile_pic']; ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="https://via.placeholder.com/120?text=Doctor" alt="Profile Placeholder">
        <?php endif; ?>
    </div>

    <form class="profile-form" id="profileForm">
        <!-- TODO: connect to database here -->
        <label>Name:</label>
        <input type="text" name="name" value="<?php echo $doctor['name']; ?>" disabled>

        <label>Specialty:</label>
        <input type="text" name="specialty" value="<?php echo $doctor['specialty']; ?>" disabled>

        <label>Email:</label>
        <input type="email" name="email" value="<?php echo $doctor['email']; ?>" disabled>

        <label>Phone:</label>
        <input type="text" name="phone" value="<?php echo $doctor['phone']; ?>" disabled>

        <label>Change Profile Picture:</label>
        <input type="file" name="profile_pic" disabled>

        <div class="buttons">
            <button type="button" id="editBtn">Edit</button>
            <button type="button" id="saveBtn">Save</button>
            <button type="button" onclick="window.location.href='dashboard.php'">Back</button>
        </div>
    </form>
</div>

<script>
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input');

    editBtn.addEventListener('click', () => {
        inputs.forEach(input => input.disabled = false);
    });

    saveBtn.addEventListener('click', () => {
        inputs.forEach(input => input.disabled = true);
        alert('Data saved temporarily (no database)');
        // TODO: send updated data to the server via AJAX/PHP
    });
</script>

</body>
</html>