import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { AuthState, User, Client } from '@/types';
import { apiService } from '@/services/api';
import toast from 'react-hot-toast';

interface AuthStore extends AuthState {
  // Actions
  login: (email: string, password: string) => Promise<boolean>;
  register: (data: any) => Promise<boolean>;
  logout: () => void;
  updateProfile: (data: any) => Promise<boolean>;
  updateClientSettings: (data: any) => Promise<boolean>;
  testFBRConnection: () => Promise<boolean>;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
}

export const useAuthStore = create<AuthStore>()(
  persist(
    (set, get) => ({
      // Initial state
      user: null,
      client: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,

      // Actions
      login: async (email: string, password: string) => {
        set({ isLoading: true });
        try {
          const response = await apiService.login(email, password);
          
          if (response.success && response.data) {
            const { token, user, client } = response.data;
            
            set({
              user,
              client,
              token,
              isAuthenticated: true,
              isLoading: false,
            });
            
            toast.success('Login successful');
            return true;
          } else {
            toast.error(response.error || 'Login failed');
            set({ isLoading: false });
            return false;
          }
        } catch (error: any) {
          console.error('Login error:', error);
          toast.error(error.response?.data?.error || 'Login failed');
          set({ isLoading: false });
          return false;
        }
      },

      register: async (data: any) => {
        set({ isLoading: true });
        try {
          const response = await apiService.register(data);
          
          if (response.success && response.data) {
            const { token, user, client } = response.data;
            
            set({
              user,
              client,
              token,
              isAuthenticated: true,
              isLoading: false,
            });
            
            toast.success('Registration successful');
            return true;
          } else {
            toast.error(response.error || 'Registration failed');
            set({ isLoading: false });
            return false;
          }
        } catch (error: any) {
          console.error('Registration error:', error);
          toast.error(error.response?.data?.error || 'Registration failed');
          set({ isLoading: false });
          return false;
        }
      },

      logout: () => {
        set({
          user: null,
          client: null,
          token: null,
          isAuthenticated: false,
          isLoading: false,
        });
        
        // Clear localStorage
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        localStorage.removeItem('client');
        
        toast.success('Logged out successfully');
      },

      updateProfile: async (data: any) => {
        set({ isLoading: true });
        try {
          const response = await apiService.updateProfile(data);
          
          if (response.success && response.data) {
            set({
              user: response.data,
              isLoading: false,
            });
            
            toast.success('Profile updated successfully');
            return true;
          } else {
            toast.error(response.error || 'Profile update failed');
            set({ isLoading: false });
            return false;
          }
        } catch (error: any) {
          console.error('Profile update error:', error);
          toast.error(error.response?.data?.error || 'Profile update failed');
          set({ isLoading: false });
          return false;
        }
      },

      updateClientSettings: async (data: any) => {
        set({ isLoading: true });
        try {
          const response = await apiService.updateClientSettings(data);
          
          if (response.success && response.data) {
            set({
              client: { ...get().client, ...response.data },
              isLoading: false,
            });
            
            toast.success('Client settings updated successfully');
            return true;
          } else {
            toast.error(response.error || 'Client settings update failed');
            set({ isLoading: false });
            return false;
          }
        } catch (error: any) {
          console.error('Client settings update error:', error);
          toast.error(error.response?.data?.error || 'Client settings update failed');
          set({ isLoading: false });
          return false;
        }
      },

      testFBRConnection: async () => {
        set({ isLoading: true });
        try {
          const response = await apiService.testFBRConnection();
          
          if (response.success) {
            set({ isLoading: false });
            toast.success(response.message || 'FBR connection successful');
            return true;
          } else {
            toast.error(response.error || 'FBR connection failed');
            set({ isLoading: false });
            return false;
          }
        } catch (error: any) {
          console.error('FBR connection test error:', error);
          toast.error(error.response?.data?.error || 'FBR connection test failed');
          set({ isLoading: false });
          return false;
        }
      },

      setLoading: (loading: boolean) => {
        set({ isLoading: loading });
      },

      setError: (error: string | null) => {
        set({ isLoading: false });
        if (error) {
          toast.error(error);
        }
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        client: state.client,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
);