CREATE DATABASE IF NOT EXISTS eiganights CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE eiganights;

-- Drop tables in reverse order of dependency to avoid foreign key constraint errors
DROP TABLE IF EXISTS friendships;
DROP TABLE IF EXISTS watchlist;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_visibility ENUM('public', 'friends_only', 'private') DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create watchlist table
CREATE TABLE watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL, -- This is the TMDB movie ID
    movie_title VARCHAR(255) NOT NULL,
    poster_path VARCHAR(255) DEFAULT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_movie (user_id, movie_id) -- Ensures a user can't add the same movie multiple times
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create ratings table
CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL, -- TMDB movie ID
    rating INT NOT NULL, -- e.g., 1 to 10
    rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_movie_rating (user_id, movie_id) -- A user can only rate a movie once (record updated if re-rated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create comments table
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL, -- TMDB movie ID
    comment TEXT NOT NULL,
    commented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    -- No unique key on (user_id, movie_id) here to allow multiple comments per movie per user,
    -- or if you only want one, the application logic in rate_comment.php handles update-if-exists.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create friendships table
CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL, 
    user_two_id INT NOT NULL, 
    status ENUM('pending', 'accepted', 'declined') NOT NULL, -- 'blocked' status removed
    action_user_id INT NOT NULL, -- ID of the user who performed the last action (sent request, accepted, etc.)
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (action_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_unique_friendship (user_one_id, user_two_id), -- Ensures one record per pair
    CONSTRAINT chk_user_order CHECK (user_one_id < user_two_id) -- Ensures user_one_id is always less than user_two_id
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
