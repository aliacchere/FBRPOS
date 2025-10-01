import React, { useState } from 'react';
import { Menu, Transition } from '@headlessui/react';
import { 
  Bars3Icon, 
  BellIcon, 
  UserCircleIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/react/24/outline';
import { useAuthStore } from '@/store/authStore';
import { User, Client } from '@/types';
import { cn } from '@/utils/cn';

interface HeaderProps {
  onMenuClick: () => void;
  user: User | null;
  client: Client | null;
}

export const Header: React.FC<HeaderProps> = ({ onMenuClick, user, client }) => {
  const { logout } = useAuthStore();
  const [notifications] = useState([]);

  const handleLogout = () => {
    logout();
  };

  return (
    <header className="bg-white shadow-sm border-b border-gray-200">
      <div className="px-4 sm:px-6 lg:px-8">
        <div className="flex h-16 items-center justify-between">
          {/* Left side */}
          <div className="flex items-center">
            <button
              type="button"
              className="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100"
              onClick={onMenuClick}
            >
              <Bars3Icon className="h-6 w-6" />
            </button>
            
            <div className="hidden lg:block ml-4">
              <h1 className="text-lg font-semibold text-gray-900">
                {client?.businessName || 'FBR POS System'}
              </h1>
            </div>
          </div>

          {/* Right side */}
          <div className="flex items-center space-x-4">
            {/* Notifications */}
            <button
              type="button"
              className="relative p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md"
            >
              <BellIcon className="h-6 w-6" />
              {notifications.length > 0 && (
                <span className="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                  {notifications.length}
                </span>
              )}
            </button>

            {/* User menu */}
            <Menu as="div" className="relative">
              <Menu.Button className="flex items-center space-x-3 p-2 rounded-md hover:bg-gray-100">
                <UserCircleIcon className="h-8 w-8 text-gray-400" />
                <div className="hidden sm:block text-left">
                  <p className="text-sm font-medium text-gray-900">
                    {user?.firstName} {user?.lastName}
                  </p>
                  <p className="text-xs text-gray-500">
                    {user?.role}
                  </p>
                </div>
              </Menu.Button>

              <Transition
                as={React.Fragment}
                enter="transition ease-out duration-100"
                enterFrom="transform opacity-0 scale-95"
                enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="transform opacity-100 scale-100"
                leaveTo="transform opacity-0 scale-95"
              >
                <Menu.Items className="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                  <Menu.Item>
                    {({ active }) => (
                      <a
                        href="/profile"
                        className={cn(
                          active ? 'bg-gray-100' : '',
                          'flex items-center px-4 py-2 text-sm text-gray-700'
                        )}
                      >
                        <UserCircleIcon className="mr-3 h-4 w-4" />
                        Profile
                      </a>
                    )}
                  </Menu.Item>
                  <Menu.Item>
                    {({ active }) => (
                      <a
                        href="/settings"
                        className={cn(
                          active ? 'bg-gray-100' : '',
                          'flex items-center px-4 py-2 text-sm text-gray-700'
                        )}
                      >
                        <Cog6ToothIcon className="mr-3 h-4 w-4" />
                        Settings
                      </a>
                    )}
                  </Menu.Item>
                  <Menu.Item>
                    {({ active }) => (
                      <button
                        onClick={handleLogout}
                        className={cn(
                          active ? 'bg-gray-100' : '',
                          'flex w-full items-center px-4 py-2 text-sm text-gray-700'
                        )}
                      >
                        <ArrowRightOnRectangleIcon className="mr-3 h-4 w-4" />
                        Sign out
                      </button>
                    )}
                  </Menu.Item>
                </Menu.Items>
              </Transition>
            </Menu>
          </div>
        </div>
      </div>
    </header>
  );
};