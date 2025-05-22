-- Create database
CREATE DATABASE IF NOT EXISTS apartment_management;
USE apartment_management;

-- Users table
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tenant') NOT NULL,
    PRIMARY KEY (id)
);

-- Tenants table
CREATE TABLE tenants (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    contact VARCHAR(100),
    apartment_id INT(11),
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Rooms table
CREATE TABLE rooms (
    id INT(11) NOT NULL AUTO_INCREMENT,
    number VARCHAR(10) NOT NULL,
    floor INT(11) NOT NULL,
    status ENUM('vacant', 'occupied', 'maintenance') DEFAULT 'vacant',
    image_path VARCHAR(255),
    PRIMARY KEY (id)
);

-- Maintenance Requests table
CREATE TABLE maintenance_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tenant_id INT(11) NOT NULL,
    request_text TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    apartment_id INT(11),
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Maintenance table
CREATE TABLE maintenance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    apartment_id INT(11),
    tenant_id INT(11),
    description TEXT,
    status ENUM('open', 'in_progress', 'closed') DEFAULT 'open',
    date_reported DATE NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Payments table
CREATE TABLE payments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tenant_id INT(11),
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL DEFAULT CURDATE(),
    status ENUM('pending', 'paid') DEFAULT 'pending',
    payment_date DATETIME,
    description TEXT,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
