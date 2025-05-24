CREATE DATABASE IF NOT EXISTS eiganights CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE eiganights;

-- Drop tables in reverse order of dependency to avoid foreign key constraint errors
DROP TABLE IF EXISTS friendships;
DROP TABLE IF EXISTS watchlist;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS faq_items;
DROP TABLE IF EXISTS site_content;
DROP TABLE IF EXISTS forum_posts;
DROP TABLE IF EXISTS forum_threads;
DROP TABLE IF EXISTS password_resets;

-- DROP TABLE IF EXISTS forum_categories; -- Optional for later, if you want categories

-- (Optional: Categories - can be added later if needed)
/*
CREATE TABLE forum_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
*/



-- ... (rest of your CREATE TABLE statements like users, watchlist, etc.)

CREATE TABLE forum_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL, -- This will be the user's title for the annotation/scene discussion

    -- >> NEW SCENE-SPECIFIC COLUMNS <<
    scene_start_time VARCHAR(12) DEFAULT NULL, -- e.g., "00:45:12" (HH:MM:SS) or just seconds
    scene_end_time VARCHAR(12) DEFAULT NULL,   -- e.g., "00:46:05" (Optional for MVP)
    scene_description_short TEXT DEFAULT NULL, -- A brief user description of what happens in the scene

    initial_post_content TEXT NOT NULL, -- This is the actual annotation/discussion starter
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_movie_id (movie_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_post_id INT DEFAULT NULL, -- For threaded replies (optional for MVP)
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_post_id) REFERENCES forum_posts(id) ON DELETE CASCADE, -- For threaded replies
    INDEX idx_thread_id (thread_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Create table for FAQ items
CREATE TABLE faq_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0, -- For ordering FAQs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Create table for general site content (like Terms, Privacy Policy)
CREATE TABLE site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'terms-and-conditions', 'privacy-policy'
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHUSEAR(255) NOT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_visibility ENUM('public', 'friends_only', 'private') 
DEFAULT 'public',
    role ENUM('user', 'admin') DEFAULT 'user' NOT NULL, -- New column for role
    is_banned TINYINT(1) DEFAULT 0 NOT NULL -- New column for ban status (0=not
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE, -- Correctly defined here
    password VARCHAR(255) NOT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_visibility ENUM('public', 'friends_only', 'private') DEFAULT 'public',
    role ENUM('user', 'admin') DEFAULT 'user' NOT NULL,
    is_banned TINYINT(1) DEFAULT 0 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;INE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

