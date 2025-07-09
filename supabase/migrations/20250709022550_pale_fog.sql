/*
  # Add payment method column to orders table

  1. New Columns
    - `payment_method` (varchar) - Stores 'cod' or 'qr' payment method
  
  2. Changes
    - Add payment_method column to orders table with default 'cod'
*/

ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(10) DEFAULT 'cod';