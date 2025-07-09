/*
  # Add custom categories for Rimbunan Cafe

  1. New Tables
    - `categories` table to store available categories
    - Admin can manage categories through interface
  
  2. Data
    - Insert predefined categories: CONTINENTAL, SOUP, ASIAN, SNACK, HOT DRINKS, ICE DRINKS, SPARKLING DRINKS, MILK SHAKE, ICE TEA SERIES, FRAPPE
  
  3. Changes
    - Create categories management system
*/

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(50) NOT NULL UNIQUE,
  category_icon VARCHAR(10) DEFAULT '🍽️',
  category_status ENUM('Active', 'Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert predefined categories
INSERT INTO categories (category_name, category_icon) VALUES
('CONTINENTAL', '🍽️'),
('SOUP', '🍲'),
('ASIAN', '🥢'),
('SNACK', '🍿'),
('HOT DRINKS', '☕'),
('ICE DRINKS', '🧊'),
('SPARKLING DRINKS', '🥤'),
('MILK SHAKE', '🥛'),
('ICE TEA SERIES', '🧋'),
('FRAPPE', '🥤');

-- Add delivery_address column to orders table if not exists
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_address TEXT;